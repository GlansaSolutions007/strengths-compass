<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class UserAnswer extends Model
{
    use HasFactory;

    protected $fillable = [
        'test_result_id',
        'question_id',
        'answer_value',
        'final_score'
    ];

    protected $casts = [
        'answer_value' => 'integer',
        'final_score' => 'float',
    ];

    /**
     * Get the final score rounded to 2 decimal places
     */
    public function getFinalScoreAttribute($value)
    {
        return $value !== null ? round((float) $value, 2) : null;
    }

    /**
     * Get the test result this answer belongs to
     */
    public function testResult()
    {
        return $this->belongsTo(TestResult::class);
    }

    /**
     * Get the question this answer is for
     */
    public function question()
    {
        return $this->belongsTo(QuestionsModel::class, 'question_id');
    }
}
