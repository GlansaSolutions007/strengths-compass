<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Test extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'description'];

    public function clusters()
    {
        return $this->belongsToMany(Cluster::class, 'test_cluster');
    }
}


