<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class QuestionTranslationImport extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'file_name',
        'total_rows',
        'inserted',
        'updated',
        'skipped',
        'failed',
        'errors',
        'status',
        'notes',
    ];

    protected $casts = [
        'errors' => 'array',
        'total_rows' => 'integer',
        'inserted' => 'integer',
        'updated' => 'integer',
        'skipped' => 'integer',
        'failed' => 'integer',
    ];

    /**
     * Get the user who performed the import
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
