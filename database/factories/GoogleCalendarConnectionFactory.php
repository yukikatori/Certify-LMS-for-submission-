<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Models\GoogleCalendarConnection;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GoogleCalendarConnection>
 */
class GoogleCalendarConnectionFactory extends Factory
{
    protected $model = GoogleCalendarConnection::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'coach_id' => User::factory()->coach(),
            'google_account_id' => 'google-'.fake()->unique()->bothify('????####'),
            'google_email' => fake()->unique()->safeEmail(),
            'access_token' => 'access-token-'.fake()->unique()->bothify('????####'),
            'refresh_token' => 'refresh-token-'.fake()->unique()->bothify('????####'),
            'token_expires_at' => now()->addHour(),
            'connected_at' => now(),
        ];
    }

    public function forCoach(User $coach): static
    {
        return $this->state(fn () => [
            'coach_id' => $coach->id,
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'token_expires_at' => now()->subMinute(),
        ]);
    }
}
