<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Enums\UserStatus;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;
use RuntimeException;

class AvatarSeeder extends Seeder
{
    public function run(): void
    {
        $avatars = [
            'admin@certify-lms.test' => ['label' => 'AD', 'color' => [37, 99, 235]],
            'coach@certify-lms.test' => ['label' => 'CT', 'color' => [22, 163, 74]],
            'student@certify-lms.test' => ['label' => 'ST', 'color' => [234, 88, 12]],
        ];

        foreach ($avatars as $email => $avatar) {
            $user = User::where('email', $email)->first();

            if ($user === null) {
                continue;
            }

            $user->forceFill([
                'avatar_url' => $this->storeAvatar(
                    filename: 'seed-'.$user->id.'.png',
                    label: $avatar['label'],
                    color: $avatar['color'],
                ),
            ])->save();
        }

        $graduated = User::query()
            ->where('status', UserStatus::Graduated)
            ->orderBy('email')
            ->first();

        if ($graduated !== null) {
            $graduated->forceFill([
                'avatar_url' => $this->storeAvatar(
                    filename: 'seed-graduated-'.$graduated->id.'.png',
                    label: 'GR',
                    color: [124, 58, 237],
                ),
            ])->save();
        }
    }

    /**
     * @param array{0:int, 1:int, 2:int} $color
     */
    private function storeAvatar(string $filename, string $label, array $color): string
    {
        $image = imagecreatetruecolor(256, 256);

        if ($image === false) {
            throw new RuntimeException('Failed to create seed avatar image.');
        }

        $background = imagecolorallocate($image, $color[0], $color[1], $color[2]);
        $text = imagecolorallocate($image, 255, 255, 255);

        if ($background === false || $text === false) {
            imagedestroy($image);

            throw new RuntimeException('Failed to allocate seed avatar colors.');
        }

        imagefilledrectangle($image, 0, 0, 256, 256, $background);

        $this->drawLabel($image, $label, $text);

        ob_start();
        $encoded = imagepng($image);
        $png = ob_get_clean();

        imagedestroy($image);

        if ($encoded === false || $png === false) {
            throw new RuntimeException('Failed to encode seed avatar image.');
        }

        $path = 'avatars/'.$filename;

        Storage::disk('public')->put($path, $png);

        return Storage::disk('public')->url($path);
    }

    /**
     * @param \GdImage $image
     */
    private function drawLabel($image, string $label, int $color): void
    {
        $fontPath = $this->fontPath();

        if ($fontPath === null) {
            $font = 5;
            $textWidth = imagefontwidth($font) * strlen($label);
            $textHeight = imagefontheight($font);

            imagestring(
                $image,
                $font,
                (int) ((256 - $textWidth) / 2),
                (int) ((256 - $textHeight) / 2),
                $label,
                $color,
            );

            return;
        }

        $fontSize = 92;
        $box = imagettfbbox($fontSize, 0, $fontPath, $label);

        if ($box === false) {
            throw new RuntimeException('Failed to measure seed avatar label.');
        }

        $textWidth = $box[2] - $box[0];
        $textHeight = $box[1] - $box[7];
        $x = (int) ((256 - $textWidth) / 2 - $box[0]);
        $y = (int) ((256 - $textHeight) / 2 + $textHeight - $box[1]);

        $drawn = imagettftext($image, $fontSize, 0, $x, $y, $color, $fontPath, $label);

        if ($drawn === false) {
            throw new RuntimeException('Failed to draw seed avatar label.');
        }
    }

    private function fontPath(): ?string
    {
        $candidates = [
            '/usr/share/fonts/truetype/dejavu/DejaVuSans-Bold.ttf',
            '/usr/share/fonts/truetype/lato/Lato-Heavy.ttf',
            '/usr/share/fonts/truetype/lato/Lato-Bold.ttf',
        ];

        foreach ($candidates as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }
}
