<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class School extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'email',
        'contact_number',
        'address',
        'city',
        'state',
        'country',
        'principal_name',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get all users belonging to this school
     */
    public function users()
    {
        return $this->hasMany(User::class);
    }
}
