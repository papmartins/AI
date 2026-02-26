<?php

namespace App\Http\Controllers;

use App\Services\AnomalyDetector;
use App\Models\AnomalyDetection;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AnomalyController extends Controller
{
    public function __construct()
    {
        // $this->middleware('auth:sanctum')->except(['stats']);
    }

    /**
     * Get anomaly detection statistics
     */
    public function stats(AnomalyDetector $detector)
    {
        $stats = $detector->getStatistics();
        
        return response()->json([
            'status' => 'success',
            'data' => $stats
        ]);
    }

    /**
     * Check current user for anomalies
     */
    public function checkMe(AnomalyDetector $detector, Request $request)
    {
        $user = $request->user();
        
        $result = $detector->checkUserAnomaly($user);
        
        return response()->json([
            'status' => 'success',
            'is_anomaly' => $result['is_anomaly'],
            'score' => $result['score'],
            'severity' => $result['severity'],
            'type' => $result['type']
        ]);
    }

    /**
     * Get all anomalies (admin only)
     */
    public function index(AnomalyDetector $detector)
    {
        $this->authorize('viewAnomalies', AnomalyDetection::class);
        
        $anomalies = $detector->detectAnomalies();
        
        return response()->json([
            'status' => 'success',
            'count' => count($anomalies),
            'data' => $anomalies
        ]);
    }

    /**
     * Get anomalies for specific user (admin only)
     */
    public function userAnomalies($userId, AnomalyDetector $detector)
    {
        $this->authorize('viewAnomalies', AnomalyDetection::class);
        
        $user = \App\Models\User::find($userId);
        if (!$user) {
            return response()->json([
                'status' => 'error',
                'message' => 'User not found'
            ], 404);
        }
        
        $result = $detector->checkUserAnomaly($user);
        
        // Get historical anomalies
        $historical = AnomalyDetection::where('user_id', $userId)
            ->orderBy('created_at', 'desc')
            ->get();
        
        return response()->json([
            'status' => 'success',
            'user' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email
            ],
            'current_analysis' => $result,
            'historical_anomalies' => $historical
        ]);
    }

    /**
     * Resolve an anomaly (admin only)
     */
    public function resolve($anomalyId)
    {
        // Check if this is a web request or API request
        $isWebRequest = request()->is('web/*');
        
        if (!$isWebRequest) {
            // For API requests, we removed auth but you might want to add it back
            // $this->authorize('resolveAnomalies', AnomalyDetection::class);
        }
        
        $anomaly = AnomalyDetection::find($anomalyId);
        
        if (!$anomaly) {
            if ($isWebRequest) {
                return back()->with('error', 'Anomaly not found');
            } else {
                return response()->json([
                    'status' => 'error',
                    'message' => 'Anomaly not found'
                ], 404);
            }
        }
        
        $anomaly->update([
            'status' => 'resolved',
            'resolved_at' => now()
        ]);
        
        if ($isWebRequest) {
            return back()->with('success', 'Anomaly marked as resolved successfully!');
        } else {
            return response()->json([
                'status' => 'success',
                'message' => 'Anomaly marked as resolved'
            ]);
        }
    }

    /**
     * Retrain anomaly detection model (admin only)
     */
    public function retrain(AnomalyDetector $detector)
    {
        // Check if this is a web request or API request
        $isWebRequest = request()->is('web/*');
        
        if (!$isWebRequest) {
            // For API requests, we removed auth but you might want to add it back
            $this->authorize('retrainAnomalyModel', AnomalyDetection::class);
        }
        
        $result = $detector->retrain();
        
        if ($isWebRequest) {
            return back()->with('success', 'Model retrained successfully! Training took ' . $result['training_time'] . ' seconds');
        } else {
            return response()->json([
                'status' => 'success',
                'message' => $result['message'],
                'training_time' => $result['training_time']
            ]);
        }
    }

    /**
     * Public endpoint to resolve anomalies (no authentication required)
     */
    public function resolvePublic($anomalyId)
    {
        $anomaly = AnomalyDetection::find($anomalyId);
        
        if (!$anomaly) {
            return response()->json([
                'status' => 'error',
                'message' => 'Anomaly not found'
            ], 404);
        }
        
        $anomaly->update([
            'status' => 'resolved',
            'resolved_at' => now()
        ]);
        
        return response()->json([
            'status' => 'success',
            'message' => 'Anomaly marked as resolved'
        ]);
    }

    /**
     * Public endpoint to retrain model (no authentication required)
     */
    public function retrainPublic(AnomalyDetector $detector)
    {
        $result = $detector->retrain();
        
        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'training_time' => $result['training_time']
        ]);
    }

    /**
     * Get anomalies by status for tab display
     */
    public function getAnomaliesByStatus($status = null)
    {
        $query = AnomalyDetection::with('user');
        
        if ($status === 'resolved') {
            $query->whereNotNull('resolved_at');
        } elseif ($status === 'unresolved') {
            $query->whereNull('resolved_at');
        }
        
        return response()->json([
            'status' => 'success',
            'data' => $query->orderBy('score', 'desc')->get()
        ]);
    }

    /**
     * Get anomaly counts by status for tab display
     */
    public function getAnomalyCounts()
    {
        $resolvedCount = AnomalyDetection::whereNotNull('resolved_at')->count();
        $unresolvedCount = AnomalyDetection::whereNull('resolved_at')->count();
        
        return response()->json([
            'status' => 'success',
            'counts' => [
                'resolved' => $resolvedCount,
                'unresolved' => $unresolvedCount,
                'total' => $resolvedCount + $unresolvedCount
            ]
        ]);
    }
}