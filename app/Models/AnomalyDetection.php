<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AnomalyDetection extends Model
{
    use HasFactory;

    protected $table = 'anomaly_detections';

    protected $fillable = [
        'user_id',
        'type',
        'score',
        'details',
        'status',
        'resolved_at'
    ];

    protected $casts = [
        'details' => 'array',
        'resolved_at' => 'datetime'
    ];

    public function user()
    {
        return $this->belongsTo(User::class);
    }
}