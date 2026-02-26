<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var array<int, string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var array<int, string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * The attributes that should be cast.
     *
     * @var array<string, string>
     */
    protected $casts = [
        'email_verified_at' => 'datetime',
        'password' => 'hashed',
        'birth_date' => 'date',
    ];

    /**
     * Accessor for age (calculated from birth_date)
     */
    public function getAgeAttribute(): ?int
    {
        if (!$this->birth_date) {
            return null;
        }
        
        return $this->birth_date->age;
    }

    public function wishlist() {
        return $this->hasMany(Wishlist::class);
    }

    public function rentals() {
        return $this->hasMany(Rental::class);
    }

    public function ratings() {
        return $this->hasMany(Rating::class);
    }
    
    public function roleForMovie($movieId) {
        return Rating::where('user_id', $this->id)->where('movie_id', $movieId)->first();
    }

    /**
     * Send the password reset notification.
     *
     * @param  string  $token
     * @return void
     */
    public function sendPasswordResetNotification($token)
    {
        // Try to get the locale from the session or default to 'en'
        $locale = session()->get('locale', 'en');
        
        $this->notify(new \App\Notifications\ResetPasswordNotification($token, $locale));
    }
}
