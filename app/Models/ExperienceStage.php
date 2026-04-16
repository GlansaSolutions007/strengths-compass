<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ExperienceStage extends Model
{
    use HasFactory;

    protected $table = 'experience_stages';

    protected $fillable = [
        'construct_id', 
        'stage_name',
        'min_years',
        'max_years',
        'description',
    ];

    protected $casts = [
        'min_years' => 'integer',
        'max_years' => 'integer',
    ];

    function construct()
    {
        return $this->belongsTo(Construct::class, 'construct_id');
    }
}
