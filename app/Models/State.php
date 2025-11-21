<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class State extends Model
{
    protected $table = 'states';
    
    public $timestamps = false;
    
    protected $fillable = [
        'id',
        'name',
        'country_id',
        'country',
        'gstcode',
    ];

    /**
     * Get the country that owns the state.
     */
    public function country()
    {
        return $this->belongsTo(Country::class, 'country_id');
    }
}
