<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ScoringRule extends Model
{
    use HasFactory;

    protected $fillable = [
        'question_id',
        'category',
        'reverse_score',
        'include_in_construct',
        'weight'
    ];

    protected $casts = [
        'reverse_score' => 'boolean',
        'include_in_construct' => 'boolean',
        'weight' => 'float',
    ];

    /**
     * Get the question this rule applies to
     */
    public function question()
    {
        return $this->belongsTo(QuestionsModel::class);
    }
}
