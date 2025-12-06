<?php

namespace App\Http\Controllers\Parent;

use App\Http\Controllers\Controller;
use App\Http\Resources\Parent\ParentFinanceHistoryResource;
use App\Http\Resources\Parent\StudentFinanceDetailResource;
use App\Services\Parent\ParentFinanceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ParentFinanceController extends Controller
{
    public function __construct(private ParentFinanceService $parentFinanceService) {}

    public function getFinanceHistory(Request $request): JsonResponse
    {
        $parent = $request->user();
        $year = $request->query('year');
        $month = $request->query('month');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $result = $this->parentFinanceService->getFinanceHistory(
            $parent->id,
            $year,
            $month,
            $startDate,
            $endDate
        );

        if ($result['status'] === 'error') {
            return response()->json($result, $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'data' => new ParentFinanceHistoryResource($result['data'])
        ], $result['code']);
    }

    public function getStudentFinanceDetail(Request $request, int $studentId): JsonResponse
    {
        $parent = $request->user();
        $year = $request->query('year');

        $result = $this->parentFinanceService->getStudentFinanceDetail($parent->id, $studentId, $year);

        if ($result['status'] === 'error') {
            return response()->json($result, $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'data' => new StudentFinanceDetailResource($result['data'])
        ], $result['code']);
    }

    public function getSppHistory(Request $request): JsonResponse
    {
        $parent = $request->user();
        $year = $request->query('year');
        $month = $request->query('month');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $result = $this->parentFinanceService->getSppHistory(
            $parent->id,
            $year,
            $month,
            $startDate,
            $endDate
        );

        if ($result['status'] === 'error') {
            return response()->json($result, $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'data' => $result['data']
        ], $result['code']);
    }

    public function getSavingsHistory(Request $request): JsonResponse
    {
        $parent = $request->user();
        $year = $request->query('year');
        $month = $request->query('month');
        $startDate = $request->query('start_date');
        $endDate = $request->query('end_date');

        $result = $this->parentFinanceService->getSavingsHistory(
            $parent->id,
            $year,
            $month,
            $startDate,
            $endDate
        );

        if ($result['status'] === 'error') {
            return response()->json($result, $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'data' => $result['data']
        ], $result['code']);
    }

    public function getSavingsSummary(Request $request): JsonResponse
    {
        $parent = $request->user();

        $result = $this->parentFinanceService->getSavingsSummary($parent->id);

        if ($result['status'] === 'error') {
            return response()->json($result, $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'data' => $result['data']
        ], $result['code']);
    }
}
