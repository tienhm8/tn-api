<?php

namespace Database\Factories;

use App\Enums\ActivityType;
use App\Models\Customer;
use App\Models\CustomerActivity;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CustomerActivity>
 */
class CustomerActivityFactory extends Factory
{
    protected $model = CustomerActivity::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'customer_id' => Customer::factory(),
            'user_id' => User::factory(),
            'type' => fake()->randomElement(ActivityType::cases())->value,
            'content' => fake()->sentence(),
        ];
    }
}
