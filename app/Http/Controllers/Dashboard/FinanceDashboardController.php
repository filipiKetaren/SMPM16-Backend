<?php

namespace App\Http\Controllers\Dashboard;

use App\Http\Controllers\Controller;
use App\Http\Resources\Dashboard\FinanceDashboardResource;
use App\Services\Dashboard\FinanceDashboardService;
use Illuminate\Http\JsonResponse;

class FinanceDashboardController extends Controller
{
    public function __construct(private FinanceDashboardService $dashboardService) {}

    public function index(): JsonResponse
    {
        $result = $this->dashboardService->getDashboardData();

        if ($result['status'] === 'error') {
            return response()->json($result, $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'data' => new FinanceDashboardResource($result['data'])
        ], $result['code']);
    }
}
