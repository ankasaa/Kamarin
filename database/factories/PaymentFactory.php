<?php

namespace Database\Factories;

use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Payment>
 */
class PaymentFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'booking_id' => Booking::factory(),
            'recorded_by_user_id' => User::factory(),
            'amount' => 350000,
            'type' => 'deposit',
            'method' => 'bank_transfer',
            'status' => 'verified',
            'payer_name' => null,
            'payment_date' => '2026-11-01',
            'reference_number' => null,
            'proof_path' => null,
        ];
    }
}
