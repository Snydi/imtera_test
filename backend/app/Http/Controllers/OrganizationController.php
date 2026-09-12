<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreOrganizationRequest;
use App\Services\OrganizationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class OrganizationController extends Controller
{
    public function __construct(private readonly OrganizationService $organizations)
    {
    }

    public function index(Request $request): JsonResponse
    {
        return response()->json([
            'data' => $this->organizations->listFor($request->user()),
        ]);
    }

    public function store(StoreOrganizationRequest $request): JsonResponse
    {
        $organization = $this->organizations->create($request->user(), $request->validated());

        return response()->json(['data' => $organization->only($this->columns())], 201);
    }

    public function refresh(Request $request, int $organization): JsonResponse
    {
        $organization = $this->organizations->queueRefresh($request->user(), $organization);

        return response()->json(['data' => $organization->only($this->columns())], 202);
    }

    public function reviews(Request $request, int $organization): JsonResponse
    {
        return response()->json($this->organizations->reviewsFor($request->user(), $organization));
    }

    public function destroy(Request $request, int $organization): Response
    {
        $this->organizations->delete($request->user(), $organization);

        return response()->noContent();
    }

    /**
     * @return list<string>
     */
    private function columns(): array
    {
        return [
            'id', 'name', 'url', 'rating', 'rating_updated_at', 'rating_count', 'review_count',
            'parsing_status', 'parsing_progress', 'parsing_error', 'data_updated_at',
        ];
    }
}
