<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Construct extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description', 'cluster_id'];

    public function cluster()
    {
        return $this->belongsTo(Cluster::class);
    }
}


