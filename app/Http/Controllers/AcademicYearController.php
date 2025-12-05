<?php

namespace App\Http\Controllers;

use App\Http\Resources\AcademicYearResource;
use App\Services\AcademicYearService;
use Illuminate\Http\Request;
use App\Http\Resources\PaginationResource;

class AcademicYearController extends Controller
{
    public function __construct(private AcademicYearService $academicYearService) {}

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

        $result = $this->academicYearService->getAllAcademicYears($filters);

        if ($result['status'] === 'error') {
            return response()->json($result, $result['code']);
        }

        $paginator = $result['data'];
        $collection = $paginator->getCollection();

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'data' => AcademicYearResource::collection($collection),
            'pagination' => new PaginationResource($paginator)
        ], $result['code']);
    }

    public function store(Request $request)
    {
        $result = $this->academicYearService->createAcademicYear($request->all());

        if ($result['status'] === 'error') {
            return response()->json($result, $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'data' => new AcademicYearResource($result['data'])
        ], $result['code']);
    }

    public function show($id)
    {
        $result = $this->academicYearService->getAcademicYearById($id);

        if ($result['status'] === 'error') {
            return response()->json($result, $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'data' => new AcademicYearResource($result['data'])
        ], $result['code']);
    }

    public function update(Request $request, $id)
    {
        $result = $this->academicYearService->updateAcademicYear($id, $request->all());

        if ($result['status'] === 'error') {
            return response()->json($result, $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'data' => new AcademicYearResource($result['data'])
        ], $result['code']);
    }

    public function destroy($id)
    {
        $result = $this->academicYearService->deleteAcademicYear($id);

        if ($result['status'] === 'error') {
            return response()->json($result, $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message']
        ], $result['code']);
    }

    public function getActiveAcademicYear()
    {
        $result = $this->academicYearService->getActiveAcademicYear();

        if ($result['status'] === 'error') {
            return response()->json($result, $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'data' => $result['data'] ? new AcademicYearResource($result['data']) : null
        ], $result['code']);
    }
}
