<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class Test extends Model
{
    use HasFactory;

    protected $fillable = ['title', 'description', 'age_group_id', 'is_active', 'source', 'sc_pro_test_id'];

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
     * Get constructs assigned to this test (via test_cluster_construct).
     * Same construct can be in different clusters for different tests.
     */
    public function constructs()
    {
        return $this->belongsToMany(Construct::class, 'test_cluster_construct')
            ->withPivot('cluster_id')
            ->withTimestamps();
    }

    /**
     * Get all constructs for this test (test-specific cluster-construct assignment).
     * Falls back to legacy cluster->constructs for tests with no test_cluster_construct rows.
     */
    public function getConstructsAttribute()
    {
        $viaPivot = $this->constructs()->get();
        if ($viaPivot->isNotEmpty()) {
            return $viaPivot;
        }

        // Legacy: constructs through clusters (when cluster_id was set on construct)
        return Construct::whereHas('cluster', function ($query) {
            $query->whereHas('tests', function ($q) {
                $q->where('tests.id', $this->id);
            });
        })->get();
    }

    /**
     * Get constructs in a specific cluster for this test.
     */
    public function getConstructsForCluster(int $clusterId)
    {
        return $this->constructs()->wherePivot('cluster_id', $clusterId)->get();
    }

    /**
     * Get all available questions (constructs assigned to this test, not yet selected).
     */
    public function getAvailableQuestionsAttribute()
    {
        $constructIds = \Illuminate\Support\Facades\DB::table('test_cluster_construct')
            ->where('test_id', $this->id)
            ->pluck('construct_id')
            ->unique()
            ->values()
            ->all();

        if (!empty($constructIds)) {
            return \App\Models\QuestionsModel::whereIn('construct_id', $constructIds)
                ->where('is_active', true)
                ->get();
        }

        // Legacy: through cluster->constructs
        return \App\Models\QuestionsModel::whereHas('construct', function ($query) {
            $query->whereHas('cluster', function ($q) {
                $q->whereHas('tests', function ($testQuery) {
                    $testQuery->where('tests.id', $this->id);
                });
            });
        })->where('is_active', true)->get();
    }

    /**
     * Get all test results for this test
     */
    public function testResults()
    {
        return $this->hasMany(TestResult::class);
    }

    /**
     * Get the age group for this test
     */
    public function ageGroup()
    {
        return $this->belongsTo(AgeGroup::class);
    }

    /**
     * Get the SC Pro test this CERC test is linked to (for CERC tests only)
     */
    public function scProTest()
    {
        return $this->belongsTo(Test::class, 'sc_pro_test_id');
    }

    /**
     * Get all CERC tests linked to this SC Pro test
     */
    public function cercTests()
    {
        return $this->hasMany(Test::class, 'sc_pro_test_id');
    }
}


