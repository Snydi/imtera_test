<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrganizationRequest;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class OrganizationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $request->user()->organizations()->latest('id')->get(['id', 'url']),
        ]);
    }

    public function store(StoreOrganizationRequest $request): JsonResponse
    {
        $organization = $request->user()->organizations()->create($request->validated());

        return response()->json(['data' => $organization->only(['id', 'url'])], 201);
    }
}
