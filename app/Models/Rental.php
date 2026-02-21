<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Rental extends Model {
    protected $fillable = ['user_id', 'movie_id', 'rented_at', 'due_date', 'returned', 'returned_at'];
    
    protected $casts = [
        'rented_at' => 'date',
        'due_date' => 'date',
        'returned_at' => 'datetime',
    ];
    
    public function user() { return $this->belongsTo(User::class); }
    public function movie() { return $this->belongsTo(Movie::class); }
}
