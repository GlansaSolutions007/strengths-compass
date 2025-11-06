<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use App\Models\QuestionsModel as Question;
use App\Models\Construct;

class QuestionsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $query = Question::with('construct');

        if (request()->has('construct_id')) {
            $query->where('construct_id', request('construct_id'));
        }

        if (request()->has('category')) {
            $query->where('category', request('category'));
        }

        if (request()->has('is_active')) {
            $query->where('is_active', filter_var(request('is_active'), FILTER_VALIDATE_BOOLEAN));
        }

        $questions = $query->orderBy('order_no')->get();

        return response()->json([
            'status' => true,
            'data' => $questions,
            'message' => 'Questions fetched successfully',
        ], 200);
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        $validator = Validator::make($request->all(), [
            'construct_id' => 'required|exists:constructs,id',
            'question_text' => 'required|string',
            'category' => 'required|in:P,R,SDB',
            'order_no' => 'required|integer',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $question = Question::create($request->only([
            'construct_id', 'question_text', 'category', 'order_no', 'is_active'
        ]));

        $question->load('construct');

        return response()->json([
            'status' => true,
            'message' => 'Question created successfully',
            'data' => $question,
        ], 201);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        $question = Question::with('construct')->find($id);

        if (!$question) {
            return response()->json([
                'status' => false,
                'message' => 'Question not found',
            ], 404);
        }

        return response()->json([
            'status' => true,
            'data' => $question,
            'message' => 'Question fetched successfully',
        ], 200);
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $question = Question::find($id);

        if (!$question) {
            return response()->json([
                'status' => false,
                'message' => 'Question not found',
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'construct_id' => 'sometimes|required|exists:constructs,id',
            'question_text' => 'sometimes|required|string',
            'category' => 'sometimes|required|in:P,R,SDB',
            'order_no' => 'sometimes|required|integer',
            'is_active' => 'sometimes|boolean',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'status' => false,
                'errors' => $validator->errors(),
            ], 422);
        }

        $question->update($request->only([
            'construct_id', 'question_text', 'category', 'order_no', 'is_active'
        ]));

        $question->load('construct');

        return response()->json([
            'status' => true,
            'message' => 'Question updated successfully',
            'data' => $question,
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $question = Question::find($id);

        if (!$question) {
            return response()->json([
                'status' => false,
                'message' => 'Question not found',
            ], 404);
        }

        $question->delete();

        return response()->json([
            'status' => true,
            'message' => 'Question deleted successfully',
        ], 200);
    }

    /**
     * List questions by construct ID
     */
    public function byConstruct(string $constructId)
    {
        $construct = Construct::find($constructId);

        if (!$construct) {
            return response()->json([
                'status' => false,
                'message' => 'Construct not found',
            ], 404);
        }

        $questions = Question::where('construct_id', $constructId)
            ->orderBy('order_no')
            ->get();

        return response()->json([
            'status' => true,
            'data' => $questions,
            'message' => 'Questions fetched successfully',
        ], 200);
    }
}
