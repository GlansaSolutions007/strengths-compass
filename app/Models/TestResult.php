<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TestResult extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'test_id',
        'total_score',
        'average_score',
        'cluster_scores',
        'construct_scores',
        'sdb_flag',
        'status',
        'expert_id'
    ];

    protected $casts = [
        'cluster_scores' => 'array',
        'construct_scores' => 'array',
        'sdb_flag' => 'boolean',
        'total_score' => 'float',
        'average_score' => 'float',
    ];

    /**
     * Get the user who took the test
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Get the test
     */
    public function test()
    {
        return $this->belongsTo(Test::class);
    }

    /**
     * Get all answers for this test result
     */
    public function answers()
    {
        return $this->hasMany(UserAnswer::class);
    }

    /**
     * Get the report for this test result
     */
    public function report()
    {
        return $this->hasOne(TestReport::class);
    }

    /**
     * Get the expert who reviewed this result
     */
    public function expert()
    {
        return $this->belongsTo(User::class, 'expert_id');
    }
}
