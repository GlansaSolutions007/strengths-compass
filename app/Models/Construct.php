<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Construct extends Model
{
    use HasFactory;

    protected $fillable = [
        'cluster_id',
        'name',
        'short_code',
        'description',
        'definition',
        'high_behavior',
        'medium_behavior',
        'low_behavior',
        'benefits',
        'risks',
        'coaching_applications',
        'case_example',
        'display_order',
        'is_active',
        'is_deleted'
    ];

    public function cluster()
    {
        return $this->belongsTo(Cluster::class);
    }
}


