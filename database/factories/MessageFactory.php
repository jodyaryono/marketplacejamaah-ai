<?php

namespace Database\Factories;

use App\Models\Message;
use Illuminate\Database\Eloquent\Factories\Factory;

class MessageFactory extends Factory
{
    protected $model = Message::class;

    public function definition(): array
    {
        return [
            'message_id'    => $this->faker->unique()->uuid(),
            'sender_number' => '628' . $this->faker->numerify('#########'),
            'sender_name'   => $this->faker->name(),
            'message_type'  => 'text',
            'raw_body'      => $this->faker->sentence(8),
            'direction'     => 'in',
            'is_processed'  => false,
            'is_ad'         => null,
        ];
    }
}
