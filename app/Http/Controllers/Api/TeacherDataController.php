<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\TeacherData;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class TeacherDataController extends Controller
{
    /**
     * GET /teacher-data
     * Display a listing of all teacher data records.
     */
    public function index(Request $request)
    {
        $query = TeacherData::with('user');

        if ($request->has('tch_user_id')) {
            $query->where('tch_user_id', $request->tch_user_id);
        }

        if ($request->has('tch_teaching_subject')) {
            $query->where('tch_teaching_subject', $request->tch_teaching_subject);
        }

        if ($request->has('tch_teaching_level')) {
            $query->where('tch_teaching_level', $request->tch_teaching_level);
        }

        $records = $query->orderBy('id')->get();

        return response()->json([
            'status'  => true,
            'data'    => $records,
            'message' => 'Teacher data fetched successfully'
        ], 200);
    }

    /**
     * POST /teacher-data
     * Store a newly created teacher data record.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'tch_user_id'             => 'required|integer|exists:users,id',
            'tch_teaching_subject'    => 'required|string|max:255',
            'tch_teaching_level'      => 'required|string|max:255',
            'tch_years_of_experience' => 'nullable|integer|min:0|max:60',
            'tch_current_role'        => 'nullable|string|max:255',
            'tch_school_context'      => 'nullable|string|max:255',
            'tch_school_name'         => 'nullable|string|max:255',
            'tch_school_city'         => 'nullable|string|max:255',
            'tch_school_state'        => 'nullable|string|max:255',
            'tch_consent'             => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'errors'  => $validator->errors(),
                'message' => 'Validation failed'
            ], 422);
        }

        $record = TeacherData::create($request->only([
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
        ]));

        $record->load('user');

        return response()->json([
            'status'  => true,
            'message' => 'Teacher data created successfully',
            'data'    => $record
        ], 201);
    }

    /**
     * GET /teacher-data/{id}
     * Display the specified teacher data record.
     */
    public function show(string $id)
    {
        $record = TeacherData::with('user')->find($id);

        if (!$record) {
            return response()->json([
                'status'  => false,
                'message' => 'Teacher data not found'
            ], 404);
        }

        return response()->json([
            'status'  => true,
            'data'    => $record,
            'message' => 'Teacher data fetched successfully'
        ], 200);
    }

    /**
     * GET /teacher-data/by-user/{userId}
     * Display all teacher data records for a specific user.
     */
    public function showByUser(string $userId)
    {
        $records = TeacherData::with('user')
            ->where('tch_user_id', $userId)
            ->get();

        return response()->json([
            'status'  => true,
            'data'    => $records,
            'message' => 'Teacher data fetched successfully'
        ], 200);
    }

    /**
     * PUT /teacher-data/{id}
     * Update the specified teacher data record.
     */
    public function update(Request $request, string $id)
    {
        $record = TeacherData::find($id);

        if (!$record) {
            return response()->json([
                'status'  => false,
                'message' => 'Teacher data not found'
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'tch_user_id'             => 'sometimes|integer|exists:users,id',
            'tch_teaching_subject'    => 'sometimes|string|max:255',
            'tch_teaching_level'      => 'sometimes|string|max:255',
            'tch_years_of_experience' => 'nullable|integer|min:0|max:60',
            'tch_current_role'        => 'nullable|string|max:255',
            'tch_school_context'      => 'nullable|string|max:255',
            'tch_school_name'         => 'nullable|string|max:255',
            'tch_school_city'         => 'nullable|string|max:255',
            'tch_school_state'        => 'nullable|string|max:255',
            'tch_consent'             => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'errors'  => $validator->errors(),
                'message' => 'Validation failed'
            ], 422);
        }

        $record->update($request->only([
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
        ]));

        $record->refresh()->load('user');

        return response()->json([
            'status'  => true,
            'message' => 'Teacher data updated successfully',
            'data'    => $record
        ], 200);
    }

    /**
     * DELETE /teacher-data/{id}
     * Remove the specified teacher data record.
     */
    public function destroy(string $id)
    {
        $record = TeacherData::find($id);

        if (!$record) {
            return response()->json([
                'status'  => false,
                'message' => 'Teacher data not found'
            ], 404);
        }

        $record->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Teacher data deleted successfully'
        ], 200);
    }
}
