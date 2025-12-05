<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Resources\Finance\SppPaymentResource;
use App\Http\Resources\Finance\StudentBillsResource;
use App\Services\Finance\SppService;
use Illuminate\Http\Request;
use Tymon\JWTAuth\Facades\JWTAuth;
use App\Http\Resources\PaginationResource;

class SppController extends Controller
{
    public function __construct(private SppService $sppService) {}

    public function getStudentsWithBills(Request $request)
    {
        // Get pagination parameters from request
        $perPage = $request->query('per_page', 5);
        $page = $request->query('page', 1);

        // Merge with filters
        $filters = array_merge($request->all(), [
            'per_page' => $perPage,
            'page' => $page
        ]);

        $result = $this->sppService->getStudentsWithBills($filters);

        if ($result['status'] === 'error') {
            return response()->json($result, $result['code']);
        }

        $paginator = $result['data'];

        $collection = $paginator->getCollection();

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'data' => StudentBillsResource::collection($collection),
            'pagination' => new PaginationResource($paginator)
        ], $result['code']);
    }

    public function getStudentPaymentHistory($studentId, Request $request)
    {

        $year = $request->query('year');

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

    /**
     * Get student SPP bills with academic year logic
     */
    public function getStudentBills($studentId)
    {
        $result = $this->sppService->generateStudentBills($studentId);

        if ($result['status'] === 'error') {
            return response()->json($result, $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'data' => $result['data']
        ], $result['code']);
    }

    /**
     * Process payment with academic year validation
     */
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
