<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Country extends Model
{
    protected $table = 'countries';
    
    public $timestamps = false;
    
    protected $fillable = [
        'id',
        'sortname',
        'name',
        'phonecode',
    ];

    /**
     * Get the states for the country.
     */
    public function states()
    {
        return $this->hasMany(State::class, 'country_id');
    }
}
