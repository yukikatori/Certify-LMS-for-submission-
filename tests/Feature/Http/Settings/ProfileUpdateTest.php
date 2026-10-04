<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_can_view_settings_profile(): void
    {
        $user = User::factory()->student()->create([
            'name' => '受講生太郎',
            'bio' => '学習中です。',
        ]);

        $response = $this->actingAs($user)->get(route('settings.profile.edit'));

        $response->assertOk();
        $response->assertSee('プロフィール設定');
        $response->assertSee('受講生太郎');
        $response->assertSee('学習中です。');
        $response->assertSee($user->email);
    }

    public function test_coach_can_view_settings_profile_with_meeting_url_field(): void
    {
        $user = User::factory()->coach()->create([
            'meeting_url' => 'https://meet.example.com/coach-room',
        ]);

        $response = $this->actingAs($user)->get(route('settings.profile.edit'));

        $response->assertOk();
        $response->assertSee('固定面談 URL');
        $response->assertSee('https://meet.example.com/coach-room');
    }

    public function test_admin_does_not_see_meeting_url_field(): void
    {
        $user = User::factory()->admin()->create();

        $response = $this->actingAs($user)->get(route('settings.profile.edit'));

        $response->assertOk();
        $response->assertDontSee('固定面談 URL');
    }

    public function test_graduated_student_can_view_settings_profile(): void
    {
        $user = User::factory()->student()->graduated()->create();

        $response = $this->actingAs($user)->get(route('settings.profile.edit'));

        $response->assertOk();
        $response->assertSee('プロフィール設定');
    }

    public function test_user_can_update_name_and_bio(): void
    {
        $user = User::factory()->student()->create([
            'name' => '変更前',
            'bio' => '変更前の自己紹介',
        ]);

        $response = $this->actingAs($user)->patch(route('settings.profile.update'), [
            'name' => '変更後',
            'bio' => '変更後の自己紹介',
        ]);

        $response->assertRedirect(route('settings.profile.edit'));
        $response->assertSessionHas('success', 'プロフィールを更新しました。');

        $user->refresh();
        $this->assertSame('変更後', $user->name);
        $this->assertSame('変更後の自己紹介', $user->bio);
    }

    public function test_email_is_not_updated_from_settings_profile(): void
    {
        $user = User::factory()->student()->create([
            'email' => 'original@example.test',
        ]);

        $response = $this->actingAs($user)->patch(route('settings.profile.update'), [
            'name' => '変更後',
            'bio' => null,
            'email' => 'changed@example.test',
        ]);

        $response->assertRedirect(route('settings.profile.edit'));

        $user->refresh();
        $this->assertSame('original@example.test', $user->email);
        $this->assertSame('変更後', $user->name);
    }

    public function test_coach_can_update_meeting_url(): void
    {
        $user = User::factory()->coach()->create([
            'meeting_url' => 'https://meet.example.com/old-room',
        ]);

        $response = $this->actingAs($user)->patch(route('settings.profile.update'), [
            'name' => $user->name,
            'bio' => $user->bio,
            'meeting_url' => 'https://meet.example.com/new-room',
        ]);

        $response->assertRedirect(route('settings.profile.edit'));
        $this->assertSame('https://meet.example.com/new-room', $user->fresh()->meeting_url);
    }

    public function test_student_cannot_update_meeting_url(): void
    {
        $user = User::factory()->student()->create([
            'meeting_url' => null,
        ]);

        $response = $this->actingAs($user)->patch(route('settings.profile.update'), [
            'name' => $user->name,
            'bio' => $user->bio,
            'meeting_url' => 'https://meet.example.com/student-room',
        ]);

        $response->assertRedirect(route('settings.profile.edit'));
        $this->assertNull($user->fresh()->meeting_url);
    }

    public function test_admin_cannot_update_meeting_url(): void
    {
        $user = User::factory()->admin()->create([
            'meeting_url' => null,
        ]);

        $response = $this->actingAs($user)->patch(route('settings.profile.update'), [
            'name' => $user->name,
            'bio' => $user->bio,
            'meeting_url' => 'https://meet.example.com/admin-room',
        ]);

        $response->assertRedirect(route('settings.profile.edit'));
        $this->assertNull($user->fresh()->meeting_url);
    }

    public function test_graduated_student_can_update_profile(): void
    {
        $user = User::factory()->student()->graduated()->create();

        $response = $this->actingAs($user)->patch(route('settings.profile.update'), [
            'name' => '修了済受講生',
            'bio' => '修了後もプロフィールを更新できます。',
        ]);

        $response->assertRedirect(route('settings.profile.edit'));
        $this->assertSame('修了済受講生', $user->fresh()->name);
    }

    public function test_name_is_required(): void
    {
        $user = User::factory()->student()->create([
            'name' => '変更前',
        ]);

        $response = $this->actingAs($user)
            ->from(route('settings.profile.edit'))
            ->patch(route('settings.profile.update'), [
                'name' => '',
                'bio' => '自己紹介',
            ]);

        $response->assertRedirect(route('settings.profile.edit'));
        $response->assertSessionHasErrors('name');
        $this->assertSame('変更前', $user->fresh()->name);
    }

    public function test_bio_must_not_exceed_max_length(): void
    {
        $user = User::factory()->student()->create();

        $response = $this->actingAs($user)
            ->from(route('settings.profile.edit'))
            ->patch(route('settings.profile.update'), [
                'name' => $user->name,
                'bio' => str_repeat('あ', 1001),
            ]);

        $response->assertRedirect(route('settings.profile.edit'));
        $response->assertSessionHasErrors('bio');
    }

    public function test_meeting_url_must_be_valid_url(): void
    {
        $user = User::factory()->coach()->create([
            'meeting_url' => 'https://meet.example.com/old-room',
        ]);

        $response = $this->actingAs($user)
            ->from(route('settings.profile.edit'))
            ->patch(route('settings.profile.update'), [
                'name' => $user->name,
                'bio' => $user->bio,
                'meeting_url' => 'not-a-url',
            ]);

        $response->assertRedirect(route('settings.profile.edit'));
        $response->assertSessionHasErrors('meeting_url');
        $this->assertSame('https://meet.example.com/old-room', $user->fresh()->meeting_url);
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->get(route('settings.profile.edit'));

        $response->assertRedirect('/login');
    }
}
