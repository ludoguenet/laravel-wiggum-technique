<?php

namespace Database\Seeders;

use App\Models\Team;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $alice = User::factory()->create([
            'name' => 'Alice',
            'email' => 'alice@example.com',
        ]);

        $bob = User::factory()->create([
            'name' => 'Bob',
            'email' => 'bob@example.com',
        ]);

        $charlie = User::factory()->create([
            'name' => 'Charlie',
            'email' => 'charlie@example.com',
        ]);

        $acme = Team::factory()->create([
            'name' => 'Acme',
            'owner_id' => $alice->id,
        ]);
        $acme->users()->attach([$alice->id, $bob->id]);

        $engineering = Team::factory()->create([
            'name' => 'Engineering',
            'owner_id' => $alice->id,
        ]);
        $engineering->users()->attach([$alice->id, $charlie->id]);

        $design = Team::factory()->create([
            'name' => 'Design',
            'owner_id' => $bob->id,
        ]);
        $design->users()->attach([$bob->id, $charlie->id]);
    }
}
