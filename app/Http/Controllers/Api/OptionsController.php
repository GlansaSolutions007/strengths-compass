<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OptionsModel;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;

class OptionsController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index()
    {
        $options = OptionsModel::get(); //getting all the rows
        if (empty($options)) {
            return response()->json([
                "data" => [],
                "status" => 404,
                "message" => "No options found",
            ], 404);
        } else {
            return response()->json([
                "data" => $options,
                "status" => 200,
                "message" => "Options fetched successfully",
            ], 200);
        }
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        //
        $value = $request->input('value');
        $label = $request->input('label');
        $createOptions = OptionsModel::create([
            'value' => $value,
            'label' => $label
        ]);
        if ($createOptions) {
            return response()->json([
                "data" => $createOptions,
                "status" => 200,
                "message" => "Option created successfully",
            ], 200);
        } else {
            return response()->json([
                "data" => [],
                "status" => 400,
                "message" => "Option not created",
            ], 400);
        }


    }

    /**
     * Display the specified resource.
     */
    public function show(string $id)
    {
        //
        $options = OptionsModel::find($id);
        if (empty($options)) {
            return response()->json([
                "data" => [],
                "status" => 404,
                "message" => "Option not found",
            ], 404);
        } else {
            return response()->json([
                "data" => $options,
                "status" => 200,
                "message" => "Option fetched successfully",
            ], 200);
        }
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, string $id)
    {
        $option = OptionsModel::find($id);
        
        if (!$option) {
            return response()->json([
                "data" => [],
                "status" => 404,
                "message" => "Option not found",
            ], 404);
        }

        $validator = Validator::make($request->all(), [
            'label' => 'sometimes|required|string|max:255',
            'value' => 'sometimes|required|integer|min:1',
        ]);

        if ($validator->fails()) {
            return response()->json([
                "data" => [],
                "status" => 422,
                "errors" => $validator->errors(),
                "message" => "Validation failed",
            ], 422);
        }

        $option->update($request->only(['label', 'value']));

        return response()->json([
            "data" => $option,
            "status" => 200,
            "message" => "Option updated successfully",
        ], 200);
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        $option = OptionsModel::find($id);
        
        if (!$option) {
            return response()->json([
                "data" => [],
                "status" => 404,
                "message" => "Option not found",
            ], 404);
        }

        $option->delete();

        return response()->json([
            "data" => [],
            "status" => 200,
            "message" => "Option deleted successfully",
        ], 200);
    }
}
