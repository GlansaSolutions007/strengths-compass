<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Construct;
use App\Models\ExperienceStage;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class ExperienceStageController extends Controller
{
    /**
     * Display a listing of all experience stages.
     */
    public function index()
    {
        $stages = ExperienceStage::orderBy('min_years')->get();

        return response()->json([
            'status' => true,
            'data' => $stages,
            'message' => 'Experience stages fetched successfully',
        ], 200);
    }

    /**
     * Store a newly created experience stage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'construct_id' => 'nullable|exists:constructs,id',
            'stage_name'  => 'required|string|max:100',
            'min_years'   => 'required|integer|min:0',
            'max_years'   => 'required|integer|min:0|gte:min_years',
            'description' => 'nullable|string',
        ]);

        $constructId = $request->input('construct_id');
        if ($constructId) {
            $construct = Construct::where('id', $constructId)
                ->where('age_group_id', 6)
                ->first();

            if (!$construct) {
                return response()->json([
                    'message' => 'Given construct id is not for age group 6'
                ], 422);
            }
        }

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'errors'  => $validator->errors(),
                'message' => 'Validation failed',
            ], 422);
        }

        $stage = ExperienceStage::create($request->only([
            'construct_id',
            'stage_name',
            'min_years',
            'max_years',
            'description',
        ]));

        return response()->json([
            'status'  => true,
            'data'    => $stage,
            'message' => 'Experience stage created successfully',
        ], 201);
    }

    /**
     * Display the specified experience stage.
     */
    public function show(string $id)
    {
        $stage = ExperienceStage::find($id);

        if (!$stage) {
            return response()->json([
                'status'  => false,
                'message' => 'Experience stage not found',
            ], 404);
        }

        return response()->json([
            'status'  => true,
            'data'    => $stage,
            'message' => 'Experience stage fetched successfully',
        ], 200);
    }

    /**
     * Update the specified experience stage.
     */
    public function update(Request $request, string $id)
    {
        $stage = ExperienceStage::find($id);

        if (!$stage) {
            return response()->json([
                'status'  => false,
                'message' => 'Experience stage not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'construct_id' => 'nullable|exists:constructs,id',
            'stage_name'  => 'sometimes|required|string|max:100',
            'min_years'   => 'sometimes|required|integer|min:0',
            'max_years'   => 'sometimes|required|integer|min:0|gte:min_years',
            'description' => 'nullable|string',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status'  => false,
                'errors'  => $validator->errors(),
                'message' => 'Validation failed',
            ], 422);
        }

        $stage->update($request->only([
            'construct_id',
            'stage_name',
            'min_years',
            'max_years',
            'description',
        ]));

        return response()->json([
            'status'  => true,
            'data'    => $stage,
            'message' => 'Experience stage updated successfully',
        ], 200);
    }

    /**
     * Remove the specified experience stage.
     */
    public function destroy(string $id)
    {
        $stage = ExperienceStage::find($id);

        if (!$stage) {
            return response()->json([
                'status'  => false,
                'message' => 'Experience stage not found',
            ], 404);
        }

        $stage->delete();

        return response()->json([
            'status'  => true,
            'message' => 'Experience stage deleted successfully',
        ], 200);
    }
}
