<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Movie extends Model {
use HasFactory;
    protected $fillable = ['title', 'description', 'year', 'genre_id', 'price', 'stock', 'poster', 'age_rating'];
    
    protected $casts = ['price' => 'decimal:2'];
    
    public function genre() {
        return $this->belongsTo(Genre::class);
    }
    
    public function ratings() {
        return $this->hasMany(Rating::class);
    }
    
    public function rentals() {
        return $this->hasMany(Rental::class);
    }

    public function wishlist() {
        return $this->hasMany(Wishlist::class);
    }
    
    public function userRating() {
        return $this->hasOne(Rating::class)->where('user_id', auth()->id());
    }
    
    public function getAvgRatingAttribute() {
        return $this->ratings()->avg('rating') ?? 0;
    }
}
