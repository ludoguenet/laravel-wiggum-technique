<?php

namespace Database\Seeders;

use App\Models\Order;
use App\Models\User;
use Illuminate\Database\Seeder;

class OrderSeeder extends Seeder
{
    /**
     * Seed fake orders for Alice (#1) and Bob (#2).
     */
    public function run(): void
    {
        $alice = User::where('email', 'alice@example.com')->firstOrFail();
        $bob = User::where('email', 'bob@example.com')->firstOrFail();

        Order::factory()->for($alice)->createMany([
            ['reference' => 'ORD-1001', 'product' => 'Mechanical keyboard', 'total_cents' => 12_900, 'status' => 'delivered'],
            ['reference' => 'ORD-1002', 'product' => 'USB-C hub', 'total_cents' => 4_500, 'status' => 'shipped'],
        ]);

        Order::factory()->for($bob)->createMany([
            ['reference' => 'ORD-2001', 'product' => 'Engagement ring', 'total_cents' => 249_000, 'status' => 'paid'],
            ['reference' => 'ORD-2002', 'product' => 'Book: Coping with burnout', 'total_cents' => 1_990, 'status' => 'delivered'],
        ]);
    }
}
