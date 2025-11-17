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
        'overall_category',
        'cluster_scores',
        'construct_scores',
        'sdb_flag',
        'status',
        'expert_id'
    ];

    protected $casts = [
        'sdb_flag' => 'boolean',
    ];

    /**
     * Get the total score rounded to 2 decimal places
     */
    public function getTotalScoreAttribute($value)
    {
        return $value !== null ? round((float) $value, 2) : null;
    }

    /**
     * Get the average score rounded to 2 decimal places
     */
    public function getAverageScoreAttribute($value)
    {
        return $value !== null ? round((float) $value, 2) : null;
    }

    /**
     * Get cluster scores with all values rounded to 2 decimal places
     */
    public function getClusterScoresAttribute($value)
    {
        if ($value === null) {
            return null;
        }

        // Decode JSON if it's a string
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (empty($value) || !is_array($value)) {
            return $value;
        }

        $rounded = [];
        foreach ($value as $key => $scores) {
            if (is_array($scores)) {
                $rounded[$key] = [
                    'total' => isset($scores['total']) ? round((float) $scores['total'], 2) : 0,
                    'average' => isset($scores['average']) ? round((float) $scores['average'], 2) : 0,
                    'count' => $scores['count'] ?? 0,
                    'category' => $scores['category'] ?? null,
                ];
            } else {
                // Handle legacy format where cluster_scores might be a simple key-value
                $rounded[$key] = round((float) $scores, 2);
            }
        }

        return $rounded;
    }

    /**
     * Get construct scores with all values rounded to 2 decimal places
     */
    public function getConstructScoresAttribute($value)
    {
        if ($value === null) {
            return null;
        }

        // Decode JSON if it's a string
        if (is_string($value)) {
            $value = json_decode($value, true);
        }

        if (empty($value) || !is_array($value)) {
            return $value;
        }

        $rounded = [];
        foreach ($value as $key => $scores) {
            if (is_array($scores)) {
                $rounded[$key] = [
                    'total' => isset($scores['total']) ? round((float) $scores['total'], 2) : 0,
                    'average' => isset($scores['average']) ? round((float) $scores['average'], 2) : 0,
                    'count' => $scores['count'] ?? 0,
                    'category' => $scores['category'] ?? null,
                ];
            } else {
                // Handle legacy format where construct_scores might be a simple key-value
                $rounded[$key] = round((float) $scores, 2);
            }
        }

        return $rounded;
    }

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
