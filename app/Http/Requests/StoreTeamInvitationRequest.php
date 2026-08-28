<?php

namespace App\Http\Requests;

use App\Enums\TeamInvitationStatus;
use App\Models\TeamInvitation;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTeamInvitationRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        $team = $this->route('team');

        return [
            'user_id' => [
                'required',
                'integer',
                'exists:users,id',
                function (string $attribute, mixed $value, Closure $fail) use ($team) {
                    if ((int) $value === $this->user()->id) {
                        $fail('You cannot invite yourself.');

                        return;
                    }

                    if ($team->users()->whereKey($value)->exists()) {
                        $fail('This user is already a member of the team.');

                        return;
                    }

                    if (TeamInvitation::where('team_id', $team->id)
                        ->where('invitee_id', $value)
                        ->where('status', TeamInvitationStatus::Pending)
                        ->exists()) {
                        $fail('This user already has a pending invitation.');
                    }
                },
            ],
        ];
    }
}
