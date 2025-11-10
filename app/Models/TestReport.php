<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TestReport extends Model
{
    use HasFactory;

    protected $fillable = [
        'test_result_id',
        'radar_data',
        'application_matrix',
        'report_summary',
        'recommendations',
        'report_file',
        'generated_at'
    ];

    protected $casts = [
        'radar_data' => 'array',
        'application_matrix' => 'array',
        'generated_at' => 'datetime',
    ];

    /**
     * Get the test result this report belongs to
     */
    public function testResult()
    {
        return $this->belongsTo(TestResult::class);
    }
}
