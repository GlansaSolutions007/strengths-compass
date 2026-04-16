<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class TeacherData extends Model
{
    use HasFactory;

    protected $table = 'teacher_data';

    protected $fillable = [
        'tch_user_id',
        'tch_teaching_subject',
        'tch_teaching_level',
        'tch_years_of_experience',
        'tch_current_role',
        'tch_school_context',
        'tch_school_name',
        'tch_school_city',
        'tch_school_state',
        'tch_consent',
    ];

    protected $casts = [
        'tch_consent' => 'boolean',
        'tch_years_of_experience' => 'integer',
    ];

    /**
     * Get the user that owns this teacher data.
     */
    public function user()
    {
        return $this->belongsTo(User::class, 'tch_user_id');
    }
}
