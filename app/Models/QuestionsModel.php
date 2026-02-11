<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionsModel extends Model
{
    protected $table="questions";
    protected $fillable = ['construct_id', 'question_text', 'age_group_id', 'category', 'order_no', 'is_active', 'source'];

    public function construct()
    {
        return $this->belongsTo(Construct::class);
    }

    public function ageGroup()
    {
        return $this->belongsTo(AgeGroup::class);
    }

    /**
     * Get all translations for this question
     */
    public function translations()
    {
        return $this->hasMany(QuestionTranslation::class, 'question_id');
    }
}
