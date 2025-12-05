<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class AgeGroup extends Model
{
    use HasFactory;

    protected $fillable = ['name', 'from', 'to', 'description', 'is_active'];

    /**
     * Get clusters for this age group
     */
    public function clusters()
    {
        return $this->hasMany(Cluster::class);
    }

    /**
     * Get constructs for this age group
     */
    public function constructs()
    {
        return $this->hasMany(Construct::class);
    }

    /**
     * Get questions for this age group
     */
    public function questions()
    {
        return $this->hasMany(QuestionsModel::class);
    }

    /**
     * Get tests for this age group
     */
    public function tests()
    {
        return $this->hasMany(Test::class);
    }
}
