<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Country;
use Illuminate\Http\Request;

class CountryController extends Controller
{
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $query = Country::query();

        // Optional: Filter by search term
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('sortname', 'like', "%{$search}%");
        }

        // Optional: Include states
        if ($request->has('with_states') && $request->input('with_states') == 'true') {
            $query->with('states');
        }

        $countries = $query->orderBy('name')->get();

        if ($countries->isEmpty()) {
            return response()->json([
                "data" => [],
                "status" => 404,
                "message" => "No countries found",
            ], 404);
        }

        return response()->json([
            "data" => $countries,
            "status" => 200,
            "message" => "Countries fetched successfully",
        ], 200);
    }

    /**
     * Display the specified resource.
     */
    public function show(string $id, Request $request)
    {
        $query = Country::query();

        // Optional: Include states
        if ($request->has('with_states') && $request->input('with_states') == 'true') {
            $query->with('states');
        }

        $country = $query->find($id);

        if (!$country) {
            return response()->json([
                "data" => [],
                "status" => 404,
                "message" => "Country not found",
            ], 404);
        }

        return response()->json([
            "data" => $country,
            "status" => 200,
            "message" => "Country fetched successfully",
        ], 200);
    }

    /**
     * Get states for a specific country.
     */
    public function getStates(string $id)
    {
        $country = Country::find($id);

        if (!$country) {
            return response()->json([
                "data" => [],
                "status" => 404,
                "message" => "Country not found",
            ], 404);
        }

        $states = $country->states()->orderBy('name')->get();

        return response()->json([
            "data" => $states,
            "status" => 200,
            "message" => "States fetched successfully",
        ], 200);
    }
}
