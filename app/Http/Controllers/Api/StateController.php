<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\State;
use Illuminate\Http\Request;

class StateController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = State::query();

        // Filter by country_id if provided
        if ($request->has('country_id')) {
            $query->where('country_id', $request->input('country_id'));
        }

        // Optional: Filter by search term
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%");
        }

        // Optional: Include country
        if ($request->has('with_country') && $request->input('with_country') == 'true') {
            $query->with('country');
        }

        $states = $query->orderBy('name')->get();

        if ($states->isEmpty()) {
            return response()->json([
                "data" => [],
                "status" => 404,
                "message" => "No states found",
            ], 404);
        }

        return response()->json([
            "data" => $states,
            "status" => 200,
            "message" => "States fetched successfully",
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id, Request $request)
    {
        $query = State::query();

        // Optional: Include country
        if ($request->has('with_country') && $request->input('with_country') == 'true') {
            $query->with('country');
        }

        $state = $query->find($id);

        if (!$state) {
            return response()->json([
                "data" => [],
                "status" => 404,
                "message" => "State not found",
            ], 404);
        }

        return response()->json([
            "data" => $state,
            "status" => 200,
            "message" => "State fetched successfully",
        ], 200);
    }
}
