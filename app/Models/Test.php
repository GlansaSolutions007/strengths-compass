<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Test extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'description', 'is_active'];

    /**
     * Get clusters associated with this test
     */
    public function clusters()
    {
        return $this->belongsToMany(Cluster::class, 'test_cluster')
            ->withPivot('p_count', 'r_count', 'sdb_count')
            ->withTimestamps();
    }

    /**
     * Get selected questions for this test
     */
    public function selectedQuestions()
    {
        return $this->belongsToMany(
            \App\Models\QuestionsModel::class,
            'test_question',
            'test_id',
            'question_id'
        )->withPivot('cluster_id', 'order_no')->orderBy('test_question.order_no');
    }

    /**
     * Get all constructs through clusters
     */
    public function getConstructsAttribute()
    {
        return Construct::whereHas('cluster', function ($query) {
            $query->whereHas('tests', function ($q) {
                $q->where('tests.id', $this->id);
            });
        })->get();
    }

    /**
     * Get all available questions through clusters and constructs (not selected, just available)
     */
    public function getAvailableQuestionsAttribute()
    {
        return \App\Models\QuestionsModel::whereHas('construct', function ($query) {
            $query->whereHas('cluster', function ($q) {
                $q->whereHas('tests', function ($testQuery) {
                    $testQuery->where('tests.id', $this->id);
                });
            });
        })->where('is_active', true)->get();
    }
}


