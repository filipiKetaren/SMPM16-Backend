<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Services\Finance\ScholarshipService;
use Illuminate\Http\Request;
use App\Http\Resources\PaginationResource;

class ScholarshipController extends Controller
{
    public function __construct(private ScholarshipService $scholarshipService) {}

    /**
     * Display a listing of scholarships.
     */
    public function index(Request $request)
    {
        // Get pagination parameters from request
        $perPage = $request->query('per_page', 5);
        $page = $request->query('page', 1);

        // Merge with filters
        $filters = array_merge($request->all(), [
            'per_page' => $perPage,
            'page' => $page
        ]);

        $result = $this->scholarshipService->getAllScholarships($filters);

        if ($result['status'] === 'error') {
            return response()->json($result, $result['code']);
        }

        $paginator = $result['data'];
        $collection = $paginator->getCollection();

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'data' => $collection,
            'pagination' => new PaginationResource($paginator)
        ], $result['code']);
    }

    /**
     * Store a newly created scholarship.
     */
    public function store(Request $request)
    {
        $result = $this->scholarshipService->createScholarship($request->all());

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
     * Display the specified scholarship.
     */
    public function show($id)
    {
        $result = $this->scholarshipService->getScholarshipDetail($id);

        if ($result['status'] === 'error') {
            return response()->json($result, $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'data' => $result['data']
        ]);
    }

    /**
     * Update the specified scholarship.
     */
    public function update(Request $request, $id)
    {
        $result = $this->scholarshipService->updateScholarship($id, $request->all());

        if ($result['status'] === 'error') {
            return response()->json($result, $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'data' => $result['data']
        ]);
    }

    /**
     * Remove the specified scholarship.
     */
    public function destroy($id)
    {
        $result = $this->scholarshipService->deleteScholarship($id);

        if ($result['status'] === 'error') {
            return response()->json($result, $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message']
        ]);
    }

    /**
     * Get active scholarships summary
     */
    public function summary()
    {
        $result = $this->scholarshipService->getScholarshipSummary();

        if ($result['status'] === 'error') {
            return response()->json($result, $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'data' => $result['data']
        ]);
    }

    /**
     * Get scholarships by student
     */
    public function byStudent($studentId)
    {
        $result = $this->scholarshipService->getScholarshipsByStudent($studentId);

        if ($result['status'] === 'error') {
            return response()->json($result, $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'data' => $result['data']
        ]);
    }
}
