<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Resources\Finance\Savings\SavingsTransactionResource;
use App\Http\Resources\Finance\Savings\StudentSavingsResource;
use App\Services\Finance\SavingsService;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use Illuminate\Support\Facades\Log;

class SavingsController extends Controller
{
    public function __construct(private SavingsService $savingsService) {}

    public function getAllStudentsWithSavings(Request $request)
    {
        try {
            $result = $this->savingsService->getAllStudentsWithSavings();

            if ($result['status'] === 'error') {
                return response()->json($result, $result['code']);
            }

            return response()->json([
                'status' => 'success',
                'message' => $result['message'],
                'data' => StudentSavingsResource::collection($result['data'])
            ], $result['code']);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan server',
                'errors' => null,
                'code' => 500
            ], 500);
        }
    }

    public function getStudentSavings($studentId)
    {
        try {
            // Validasi studentId
            if (!is_numeric($studentId) || $studentId <= 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'ID siswa tidak valid',
                    'errors' => null,
                    'code' => 422
                ], 422);
            }

            $result = $this->savingsService->getStudentSavings((int) $studentId);

            if ($result['status'] === 'error') {
                return response()->json($result, $result['code']);
            }

            return response()->json([
                'status' => 'success',
                'message' => $result['message'],
                'data' => new StudentSavingsResource($result['data'])
            ], $result['code']);

        } catch (\Exception $e) {
            return response()->json([
                'status' => 'error',
                'message' => 'Terjadi kesalahan server',
                'errors' => null,
                'code' => 500
            ], 500);
        }
    }

    public function processTransaction(Request $request)
{
    try {
        $user = JWTAuth::parseToken()->authenticate();


        $result = $this->savingsService->processTransaction($request->all(), $user->id);

        if ($result['status'] === 'error') {
            return response()->json($result, $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'data' => new SavingsTransactionResource($result['data'])
        ], $result['code']);

    } catch (\Exception $e) {
       return response()->json([
            'message' => $e->getMessage(),
            'trace' => $e->getTraceAsString(),
            'request' => $request->all()
        ]);

        return response()->json([
            'status' => 'error',
            'message' => 'Terjadi kesalahan server: ' . $e->getMessage(),
            'errors' => null,
            'code' => 500
        ], 500);
    }
}

    public function getTransactionDetail($transactionId)
    {
        $result = $this->savingsService->getTransactionDetail($transactionId);

        if ($result['status'] === 'error') {
            return response()->json($result, $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'data' => new SavingsTransactionResource($result['data'])
        ], $result['code']);
    }

    public function updateTransaction($transactionId, Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();
        $result = $this->savingsService->updateTransaction($transactionId, $request->all(), $user->id);

        if ($result['status'] === 'error') {
            return response()->json($result, $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'data' => new SavingsTransactionResource($result['data'])
        ], $result['code']);
    }

    public function deleteTransaction($transactionId)
    {
        $result = $this->savingsService->deleteTransaction($transactionId);

        if ($result['status'] === 'error') {
            return response()->json($result, $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message']
        ], $result['code']);
    }
}
