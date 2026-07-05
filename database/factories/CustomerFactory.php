<?php

namespace Database\Factories;

use App\Enums\CustomerSource;
use App\Enums\CustomerStatus;
use App\Enums\LeadSource;
use App\Models\Customer;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Customer>
 */
class CustomerFactory extends Factory
{
    protected $model = Customer::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'code' => 'KH'.str_pad((string) fake()->unique()->numberBetween(1, 999999), 6, '0', STR_PAD_LEFT),
            'company_name' => fake()->company(),
            'phone' => fake()->numerify('09########'),
            'email' => fake()->optional()->companyEmail(),
            'contact_name' => fake()->name(),
            'address' => fake()->optional()->address(),
            'lead_source' => fake()->randomElement(LeadSource::cases())->value,
            'initial_note' => fake()->optional()->sentence(),
            'marketing_note' => fake()->optional()->sentence(),
            'status' => CustomerStatus::New->value,
            'source' => CustomerSource::Manual->value,
        ];
    }

    /**
     * Khách đã được gán cho một sale.
     */
    public function assignedTo(int $saleId): static
    {
        return $this->state(fn () => [
            'assigned_to' => $saleId,
            'assigned_at' => now(),
            'status' => CustomerStatus::Assigned->value,
        ]);
    }
}
