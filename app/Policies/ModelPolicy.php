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

    /**
     * Determine if the user can view anomalies
     */
    public function viewAnomalies(User $user): bool
    {
        // For now, allow all authenticated users to view anomalies
        // In production, you might want to restrict this to admins
        return true;
    }

    /**
     * Determine if the user can resolve anomalies
     */
    public function resolveAnomalies(User $user): bool
    {
        // For now, allow all authenticated users to resolve anomalies
        // In production, you might want to restrict this to admins
        return true;
    }

    /**
     * Determine if the user can retrain anomaly model
     */
    public function retrainAnomalyModel(User $user): bool
    {
        // For now, allow all authenticated users to retrain anomaly model
        // In production, you might want to restrict this to admins
        return true;
    }
}