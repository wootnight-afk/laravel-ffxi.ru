<?php

namespace Database\Factories;

use App\Models\GameCharacter;
use App\Models\GameServer;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<GameCharacter>
 */
class GameCharacterFactory extends Factory
{
    protected $model = GameCharacter::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'game_server_id' => GameServer::factory(),
            'name' => fake()->unique()->firstName(),
            'is_public' => true,
        ];
    }

    public function hidden(): static
    {
        return $this->state(fn (array $attributes) => ['is_public' => false]);
    }
}
