<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuestionTranslation extends Model
{
    use HasFactory;

    protected $fillable = [
        'question_id',
        'language_id',
        'translated_text',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Get the language that owns this translation
     */
    public function language()
    {
        return $this->belongsTo(Language::class);
    }

    /**
     * Get the question that owns this translation
     */
    public function question()
    {
        return $this->belongsTo(QuestionsModel::class, 'question_id');
    }
}
