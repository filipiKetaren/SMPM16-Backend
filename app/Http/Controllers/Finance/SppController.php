<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Resources\Finance\SppPaymentResource;
use App\Http\Resources\Finance\StudentBillsResource;
use App\Services\Finance\SppService;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;

class SppController extends Controller
{
    public function __construct(private SppService $sppService) {}

    public function getStudentsWithBills(Request $request)
    {
        $result = $this->sppService->getStudentsWithBills($request->all());

        if ($result['status'] === 'error') {
            return response()->json($result, $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'data' => StudentBillsResource::collection($result['data'])
        ], $result['code']);
    }

    public function getStudentPaymentHistory($studentId, Request $request)
    {
        $year = $request->query('year', date('Y'));
        $result = $this->sppService->getStudentPaymentHistory($studentId, $year);

        if ($result['status'] === 'error') {
            return response()->json($result, $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'data' => $result['data']
        ], $result['code']);
    }

    public function getStudentBills($studentId, Request $request)
    {
        $year = $request->query('year', date('Y'));
        $result = $this->sppService->getStudentBills($studentId, $year);

        if ($result['status'] === 'error') {
            return response()->json($result, $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'data' => new StudentBillsResource($result['data'])
        ], $result['code']);
    }

    public function processPayment(Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();
        $result = $this->sppService->processPayment($request->all(), $user->id);

        if ($result['status'] === 'error') {
            return response()->json($result, $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'data' => new SppPaymentResource($result['data'])
        ], $result['code']);
    }

    public function getPaymentDetail($paymentId)
    {
        $result = $this->sppService->getPaymentDetail($paymentId);

        if ($result['status'] === 'error') {
            return response()->json($result, $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'data' => new SppPaymentResource($result['data'])
        ], $result['code']);
    }

    public function updatePayment($paymentId, Request $request)
    {
        $user = JWTAuth::parseToken()->authenticate();
        $result = $this->sppService->updatePayment($paymentId, $request->all(), $user->id);

        if ($result['status'] === 'error') {
            return response()->json($result, $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'data' => new SppPaymentResource($result['data'])
        ], $result['code']);
    }

    public function deletePayment($paymentId)
    {
        $result = $this->sppService->deletePayment($paymentId);

        if ($result['status'] === 'error') {
            return response()->json($result, $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message']
        ], $result['code']);
    }
}
