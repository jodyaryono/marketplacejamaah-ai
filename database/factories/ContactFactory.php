<?php

namespace Database\Factories;

use App\Models\Contact;
use Illuminate\Database\Eloquent\Factories\Factory;

class ContactFactory extends Factory
{
    protected $model = Contact::class;

    public function definition(): array
    {
        return [
            'phone_number'      => '628' . $this->faker->unique()->numerify('#########'),
            'name'              => $this->faker->name(),
            'honorific'         => $this->faker->randomElement(['Pak', 'Bu', 'Mas', 'Mbak', 'Kak']),
            'is_registered'     => false,
            'onboarding_status' => null,
            'member_role'       => null,
            'warning_count'     => 0,
            'total_violations'  => 0,
            'is_blocked'        => false,
            'message_count'     => 0,
            'ad_count'          => 0,
        ];
    }
}
