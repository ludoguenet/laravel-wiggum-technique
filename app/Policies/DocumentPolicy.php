<?php

namespace App\Policies;

use App\Models\Document;
use App\Models\User;

class DocumentPolicy
{
    /**
     * Determine whether the user can read the document.
     */
    public function view(User $user, Document $document): bool
    {
        return ! $document->is_confidential || $user->is_admin;
    }
}
