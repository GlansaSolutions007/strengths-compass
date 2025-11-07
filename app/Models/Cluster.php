<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\Construct;
use App\Models\Test;

class Cluster extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'short_code', 'description'];

    public function constructs()
    {
        return $this->hasMany(Construct::class);
    }

    public function tests()
    {
        return $this->belongsToMany(Test::class, 'test_cluster');
    }
}
