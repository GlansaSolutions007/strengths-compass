<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\OptionsModel;
use Illuminate\Http\Request;

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
        //
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(string $id)
    {
        //
    }
}
