<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Genre extends Model {
use HasFactory;
    protected $fillable = ['name_en', 'name_pt', 'name_es'];
    
    public function movies() {
        return $this->hasMany(Movie::class);
    }
    
    /**
     * Get the genre name based on the current app locale
     * 
     * @return string
     */
    public function getNameAttribute(): string
    {
        $locale = app()->getLocale();
        
        return match ($locale) {
            'pt' => $this->name_pt,
            'es' => $this->name_es,
            default => $this->name_en,
        };
    }
}
