<?php

namespace Database\Factories;

use App\Models\GameServer;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<GameServer>
 */
class GameServerFactory extends Factory
{
    protected $model = GameServer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $name = 'Server '.fake()->unique()->numberBetween(1, 9999);

        return [
            'name' => $name,
            'slug' => Str::slug($name),
            'description' => null,
            'sort_order' => 0,
            'is_active' => true,
        ];
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }
}
