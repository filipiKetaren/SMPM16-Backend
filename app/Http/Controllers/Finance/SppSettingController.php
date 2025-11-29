<?php

namespace App\Http\Controllers\Finance;

use App\Http\Controllers\Controller;
use App\Http\Resources\Finance\SppSettingResource;
use App\Services\Finance\SppSettingService;
use Illuminate\Http\Request;

class SppSettingController extends Controller
{
    public function __construct(private SppSettingService $sppSettingService) {}

    public function index(Request $request)
    {
        $result = $this->sppSettingService->getAllSettings($request->all());

        if ($result['status'] === 'error') {
            return response()->json($result, $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'data' => SppSettingResource::collection($result['data'])
        ], $result['code']);
    }

    public function store(Request $request)
    {
        $result = $this->sppSettingService->createSetting($request->all());

        if ($result['status'] === 'error') {
            return response()->json($result, $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'data' => new SppSettingResource($result['data'])
        ], $result['code']);
    }

    public function show($id)
    {
        $result = $this->sppSettingService->getSettingById($id);

        if ($result['status'] === 'error') {
            return response()->json($result, $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'data' => new SppSettingResource($result['data'])
        ], $result['code']);
    }

    public function update(Request $request, $id)
    {
        $result = $this->sppSettingService->updateSetting($id, $request->all());

        if ($result['status'] === 'error') {
            return response()->json($result, $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'data' => new SppSettingResource($result['data'])
        ], $result['code']);
    }

    public function destroy($id)
    {
        $result = $this->sppSettingService->deleteSetting($id);

        if ($result['status'] === 'error') {
            return response()->json($result, $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message']
        ], $result['code']);
    }

    public function getByAcademicYear($academicYearId)
    {
        $result = $this->sppSettingService->getSettingsByAcademicYear($academicYearId);

        if ($result['status'] === 'error') {
            return response()->json($result, $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'data' => SppSettingResource::collection($result['data'])
        ], $result['code']);
    }

    public function getActiveSettings()
    {
        $result = $this->sppSettingService->getActiveSettings();

        if ($result['status'] === 'error') {
            return response()->json($result, $result['code']);
        }

        return response()->json([
            'status' => 'success',
            'message' => $result['message'],
            'data' => SppSettingResource::collection($result['data'])
        ], $result['code']);
    }
}
