<?php

namespace Database\Factories;

use App\Enums\TeamInvitationStatus;
use App\Models\Team;
use App\Models\TeamInvitation;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<TeamInvitation>
 */
class TeamInvitationFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'team_id' => Team::factory(),
            'inviter_id' => User::factory(),
            'invitee_id' => User::factory(),
            'status' => TeamInvitationStatus::Pending,
        ];
    }
}
