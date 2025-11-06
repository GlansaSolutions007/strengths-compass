<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class QuestionsModel extends Model
{
    protected $table="questions";
    protected $fillable = ['construct_id', 'question_text', 'category', 'order_no', 'is_active'];

    public function construct()
    {
        return $this->belongsTo(Construct::class);
    }
}
