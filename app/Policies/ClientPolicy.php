<?php

namespace App\Policies;

use App\Models\User;
use App\Models\Client;

class ClientPolicy
{
    /**
     * Determine if the user can view any clients.
     */
    public function viewAny(User $user): bool
    {
        return in_array($user->role, ['admin', 'staff']);
    }

    /**
     * Determine if the user can view the client.
     */
    public function view(User $user, Client $client): bool
    {
        if (in_array($user->role, ['admin', 'staff', 'consultation_staff', 'compliance_officer'])) {
            return true;
        }

        if ($user->role === 'counsellor' && $user->training_counsellor_id) {
            return (int) $client->matched_tc_id === (int) $user->training_counsellor_id;
        }

        return false;
    }

    /**
     * Determine if the user can create clients.
     */
    public function create(User $user): bool
    {
        return in_array($user->role, ['admin', 'staff']);
    }

    /**
     * Determine if the user can update the client.
     */
    public function update(User $user, Client $client): bool
    {
        return in_array($user->role, ['admin', 'staff']);
    }

    /**
     * Determine if the user can delete the client.
     */
    public function delete(User $user, Client $client): bool
    {
        return $user->role === 'admin';
    }
}

