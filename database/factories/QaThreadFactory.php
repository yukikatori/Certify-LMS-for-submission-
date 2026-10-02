<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Enums\QaThreadStatus;
use App\Models\Certification;
use App\Models\QaThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<QaThread>
 */
class QaThreadFactory extends Factory
{
    protected $model = QaThread::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory()->student(),
            'certification_id' => Certification::factory(),
            'title' => fake()->sentence(3),
            'body' => fake()->realText(120),
            'status' => QaThreadStatus::Unresolved->value,
        ];
    }

    public function unresolved(): static
    {
        return $this->state(fn () => [
            'status' => QaThreadStatus::Unresolved->value,
        ]);
    }

    public function resolved(): static
    {
        return $this->state(fn () => [
            'status' => QaThreadStatus::Resolved->value,
        ]);
    }

    public function forStudent(User $student): static
    {
        return $this->state(fn () => [
            'user_id' => $student->id,
        ]);
    }

    public function forCertification(Certification $certification): static
    {
        return $this->state(fn () => [
            'certification_id' => $certification->id,
        ]);
    }
}
