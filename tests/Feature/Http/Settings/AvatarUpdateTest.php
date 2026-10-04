<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AvatarUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_upload_avatar(): void
    {
        Storage::fake('public');

        $user = User::factory()->student()->create();
        $file = UploadedFile::fake()->image('avatar.png', 256, 256);

        $response = $this->actingAs($user)->post(route('settings.avatar.store'), [
            'avatar' => $file,
        ]);

        $response->assertRedirect(route('settings.profile.edit'));
        $response->assertSessionHas('success', 'アイコン画像を登録しました。');

        $avatarUrl = $user->fresh()->avatar_url;
        $this->assertNotNull($avatarUrl);

        $path = ltrim(str_replace('/storage/', '', parse_url($avatarUrl, PHP_URL_PATH)), '/');
        Storage::disk('public')->assertExists($path);
    }

    public function test_uploading_new_avatar_deletes_old_avatar(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/old-avatar.png', 'old');

        $user = User::factory()->student()->create([
            'avatar_url' => Storage::disk('public')->url('avatars/old-avatar.png'),
        ]);

        $file = UploadedFile::fake()->image('new-avatar.jpg', 256, 256);

        $response = $this->actingAs($user)->post(route('settings.avatar.store'), [
            'avatar' => $file,
        ]);

        $response->assertRedirect(route('settings.profile.edit'));

        Storage::disk('public')->assertMissing('avatars/old-avatar.png');

        $avatarUrl = $user->fresh()->avatar_url;
        $this->assertNotNull($avatarUrl);

        $path = ltrim(str_replace('/storage/', '', parse_url($avatarUrl, PHP_URL_PATH)), '/');
        Storage::disk('public')->assertExists($path);
    }

    public function test_user_can_delete_avatar(): void
    {
        Storage::fake('public');
        Storage::disk('public')->put('avatars/avatar.png', 'avatar');

        $user = User::factory()->student()->create([
            'avatar_url' => Storage::disk('public')->url('avatars/avatar.png'),
        ]);

        $response = $this->actingAs($user)->delete(route('settings.avatar.destroy'));

        $response->assertRedirect(route('settings.profile.edit'));
        $response->assertSessionHas('success', 'アイコン画像を削除しました。');
        $this->assertNull($user->fresh()->avatar_url);
        Storage::disk('public')->assertMissing('avatars/avatar.png');
    }

    public function test_graduated_student_can_upload_and_delete_avatar(): void
    {
        Storage::fake('public');

        $user = User::factory()->student()->graduated()->create();
        $file = UploadedFile::fake()->image('avatar.webp', 256, 256);

        $uploadResponse = $this->actingAs($user)->post(route('settings.avatar.store'), [
            'avatar' => $file,
        ]);

        $uploadResponse->assertRedirect(route('settings.profile.edit'));
        $this->assertNotNull($user->fresh()->avatar_url);

        $deleteResponse = $this->actingAs($user)->delete(route('settings.avatar.destroy'));

        $deleteResponse->assertRedirect(route('settings.profile.edit'));
        $this->assertNull($user->fresh()->avatar_url);
    }

    public function test_png_jpg_jpeg_and_webp_are_allowed(): void
    {
        Storage::fake('public');

        foreach (['png', 'jpg', 'jpeg', 'webp'] as $extension) {
            $user = User::factory()->student()->create();
            $file = UploadedFile::fake()->image('avatar.'.$extension, 256, 256);

            $response = $this->actingAs($user)->post(route('settings.avatar.store'), [
                'avatar' => $file,
            ]);

            $response->assertRedirect(route('settings.profile.edit'));
            $this->assertNotNull($user->fresh()->avatar_url);
        }
    }

    public function test_svg_is_rejected(): void
    {
        Storage::fake('public');

        $user = User::factory()->student()->create();
        $file = UploadedFile::fake()->create('avatar.svg', 10, 'image/svg+xml');

        $response = $this->actingAs($user)
            ->from(route('settings.profile.edit'))
            ->post(route('settings.avatar.store'), [
                'avatar' => $file,
            ]);

        $response->assertRedirect(route('settings.profile.edit'));
        $response->assertSessionHasErrors('avatar');
        $this->assertNull($user->fresh()->avatar_url);
    }

    public function test_oversized_avatar_is_rejected(): void
    {
        Storage::fake('public');

        $user = User::factory()->student()->create();
        $file = UploadedFile::fake()->create('avatar.png', 2049, 'image/png');

        $response = $this->actingAs($user)
            ->from(route('settings.profile.edit'))
            ->post(route('settings.avatar.store'), [
                'avatar' => $file,
            ]);

        $response->assertRedirect(route('settings.profile.edit'));
        $response->assertSessionHasErrors('avatar');
        $this->assertNull($user->fresh()->avatar_url);
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->post(route('settings.avatar.store'), [
            'avatar' => UploadedFile::fake()->image('avatar.png', 256, 256),
        ]);

        $response->assertRedirect('/login');
    }
}
