<?php

namespace App\Policies;

use App\Models\User;

class ModelPolicy
{
    /**
     * Determine if the user can retrain models
     */
    public function retrainModels(User $user): bool
    {
        // For now, allow all authenticated users to retrain
        // In production, you might want to restrict this to admins
        return true;
    }
}