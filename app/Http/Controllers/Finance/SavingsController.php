<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Resources\Finance\Savings\SavingsTransactionResource;
use App\Http\Resources\Finance\Savings\StudentSavingsResource;
use App\Services\Finance\SavingsService;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class SavingsController extends Controller
{
    public function __construct(private SavingsService $savingsService) {}

    public function getAllStudentsWithSavings(Request $request)
    {
        $result = $this->savingsService->getAllStudentsWithSavings();

        if ($result['status'] === 'error') {
            return response()->json($result, $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'data' => StudentSavingsResource::collection($result['data'])
        ], $result['code']);
    }

    public function getStudentSavings($studentId)
    {
        // Validasi studentId
        if (!is_numeric($studentId) || $studentId <= 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'ID siswa tidak valid',
                'errors' => ['student_id' => ['ID siswa harus berupa angka positif']],
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
                'status' => 'error',
                'message' => 'Terjadi kesalahan server',
                'errors' => null,
                'code' => 500
            ], 500);
        }
    }

    public function getTransactionDetail($transactionId)
    {
        // Validasi transactionId
        if (!is_numeric($transactionId) || $transactionId <= 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'ID transaksi tidak valid',
                'errors' => ['transaction_id' => ['ID transaksi harus berupa angka positif']],
                'code' => 422
            ], 422);
        }

        $result = $this->savingsService->getTransactionDetail((int) $transactionId);

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
        try {
            // Validasi transactionId
            if (!is_numeric($transactionId) || $transactionId <= 0) {
                return response()->json([
                    'status' => 'error',
                    'message' => 'ID transaksi tidak valid',
                    'errors' => ['transaction_id' => ['ID transaksi harus berupa angka positif']],
                    'code' => 422
                ], 422);
            }

            $user = JWTAuth::parseToken()->authenticate();
            $result = $this->savingsService->updateTransaction((int) $transactionId, $request->all(), $user->id);

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
                'status' => 'error',
                'message' => 'Terjadi kesalahan server',
                'errors' => null,
                'code' => 500
            ], 500);
        }
    }

    public function deleteTransaction($transactionId)
    {
        // Validasi transactionId
        if (!is_numeric($transactionId) || $transactionId <= 0) {
            return response()->json([
                'status' => 'error',
                'message' => 'ID transaksi tidak valid',
                'errors' => ['transaction_id' => ['ID transaksi harus berupa angka positif']],
                'code' => 422
            ], 422);
        }

        $result = $this->savingsService->deleteTransaction((int) $transactionId);

        if ($result['status'] === 'error') {
            return response()->json($result, $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message']
        ], $result['code']);
    }
}
