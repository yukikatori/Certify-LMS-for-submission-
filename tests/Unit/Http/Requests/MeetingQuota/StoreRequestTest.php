<?php

declare(strict_types=1);

namespace Tests\Unit\Http\Requests\MeetingQuota;

use App\Http\Requests\MeetingQuota\StoreRequest;
use App\Models\MeetingPack;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * 追加面談購入 StoreRequest の rules() を検証する Unit テスト。
 * 公開状態・購入者のロール/受講状態は MeetingPackPolicy::purchase の責務として分離する。
 */
class StoreRequestTest extends TestCase
{
    use RefreshDatabase;

    public function test_validation_passes_with_existing_meeting_pack(): void
    {
        $pack = MeetingPack::factory()->published()->create();

        $validator = Validator::make(
            ['meeting_pack_id' => $pack->id],
            (new StoreRequest)->rules(),
        );

        $this->assertTrue($validator->passes());
    }

    public function test_validation_passes_with_existing_non_published_meeting_pack_because_policy_guards_purchase(): void
    {
        $pack = MeetingPack::factory()->draft()->create();

        $validator = Validator::make(
            ['meeting_pack_id' => $pack->id],
            (new StoreRequest)->rules(),
        );

        $this->assertTrue($validator->passes());
    }

    #[DataProvider('invalidPayloads')]
    public function test_validation_fails(array $overrides, string $expectedErrorField): void
    {
        $payload = array_merge([
            'meeting_pack_id' => (string) Str::ulid(),
        ], $overrides);

        $validator = Validator::make($payload, (new StoreRequest)->rules());

        $this->assertArrayHasKey($expectedErrorField, $validator->errors()->toArray());
    }

    /**
     * @return array<string, array{0: array<string, mixed>, 1: string}>
     */
    public static function invalidPayloads(): array
    {
        return [
            'meeting_pack_id 未指定で 422' => [['meeting_pack_id' => ''], 'meeting_pack_id'],
            'meeting_pack_id ULID 形式でないと 422' => [['meeting_pack_id' => 'not-a-ulid'], 'meeting_pack_id'],
            'meeting_pack_id 存在しない ULID で 422' => [[], 'meeting_pack_id'],
        ];
    }

    public function test_admin_cannot_purchase_meeting_pack(): void
    {
        $admin = User::factory()->admin()->create();
        $pack = MeetingPack::factory()->published()->create();

        $this->actingAs($admin)
            ->post(route('meeting-quota.checkout.create'), ['meeting_pack_id' => $pack->id])
            ->assertForbidden();
    }

    public function test_coach_cannot_purchase_meeting_pack(): void
    {
        $coach = User::factory()->coach()->create();
        $pack = MeetingPack::factory()->published()->create();

        $this->actingAs($coach)
            ->post(route('meeting-quota.checkout.create'), ['meeting_pack_id' => $pack->id])
            ->assertForbidden();
    }
}
