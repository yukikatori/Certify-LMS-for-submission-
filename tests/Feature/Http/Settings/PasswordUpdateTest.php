<?php

declare(strict_types=1);

namespace Tests\Feature\Http\Settings;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class PasswordUpdateTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_update_password_from_settings_route(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('current-password'),
        ]);

        $response = $this->actingAs($user)->put(route('settings.password.update'), [
            'current_password' => 'current-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertRedirect(route('settings.profile.edit', ['tab' => 'password']));
        $response->assertSessionHas('status', 'パスワードを更新しました。');
        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_graduated_student_can_update_password(): void
    {
        $user = User::factory()->student()->graduated()->create([
            'password' => Hash::make('current-password'),
        ]);

        $response = $this->actingAs($user)->put(route('settings.password.update'), [
            'current_password' => 'current-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertRedirect(route('settings.profile.edit', ['tab' => 'password']));
        $this->assertTrue(Hash::check('new-password', $user->fresh()->password));
    }

    public function test_current_password_must_match(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('current-password'),
        ]);

        $response = $this->actingAs($user)->from(route('settings.profile.edit', ['tab' => 'password']))
            ->put(route('settings.password.update'), [
                'current_password' => 'wrong-password',
                'password' => 'new-password',
                'password_confirmation' => 'new-password',
            ]);

        $response->assertRedirect(route('settings.profile.edit', ['tab' => 'password']));
        $response->assertSessionHasErrors('current_password', null, 'updatePassword');
        $this->assertTrue(Hash::check('current-password', $user->fresh()->password));
    }

    public function test_password_must_be_confirmed(): void
    {
        $user = User::factory()->create([
            'password' => Hash::make('current-password'),
        ]);

        $response = $this->actingAs($user)->from(route('settings.profile.edit', ['tab' => 'password']))
            ->put(route('settings.password.update'), [
                'current_password' => 'current-password',
                'password' => 'new-password',
                'password_confirmation' => 'different-password',
            ]);

        $response->assertRedirect(route('settings.profile.edit', ['tab' => 'password']));
        $response->assertSessionHasErrors('password', null, 'updatePassword');
        $this->assertTrue(Hash::check('current-password', $user->fresh()->password));
    }

    public function test_unauthenticated_user_is_redirected_to_login(): void
    {
        $response = $this->put(route('settings.password.update'), [
            'current_password' => 'current-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

        $response->assertRedirect('/login');
    }
}
