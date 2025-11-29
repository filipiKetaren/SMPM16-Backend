<?php

namespace App\Services\Finance;

use App\DTOs\SppSettingData;
use App\Repositories\Interfaces\AcademicYearRepositoryInterface;
use App\Repositories\Interfaces\SppSettingRepositoryInterface;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SppSettingService extends BaseService
{
    public function __construct(
        private SppSettingRepositoryInterface $sppSettingRepository,
        private AcademicYearRepositoryInterface $academicYearRepository
    ) {}

    public function getAllSettings(array $filters = [])
    {
        try {
            $settings = $this->sppSettingRepository->getAllSettings($filters);
            return $this->success($settings, 'Data pengaturan SPP berhasil diambil', 200);
        } catch (\Exception $e) {
            return $this->serverError('Gagal mengambil data pengaturan SPP', $e);
        }
    }

    public function getSettingById(int $id)
    {
        try {
            $setting = $this->sppSettingRepository->getSettingById($id);

            if (!$setting) {
                return $this->notFoundError('Pengaturan SPP tidak ditemukan');
            }

            return $this->success($setting, 'Detail pengaturan SPP berhasil diambil', 200);
        } catch (\Exception $e) {
            return $this->serverError('Gagal mengambil detail pengaturan SPP', $e);
        }
    }

    public function createSetting(array $data)
    {
        DB::beginTransaction();
        try {
            // Validasi tipe data terlebih dahulu
            $typeValidation = $this->validateDataTypes($data);
            if ($typeValidation) {
                return $typeValidation;
            }

            // Validasi input required fields
            $validationResult = $this->validateRequiredFields($data);
            if ($validationResult) {
                return $validationResult;
            }

            $settingData = SppSettingData::fromRequest($data);

            // Validasi business logic
            $validationResult = $this->validateSettingData($settingData);
            if ($validationResult['status'] === 'error') {
                return $validationResult;
            }

            // Cek duplikasi
            $existingSetting = $this->sppSettingRepository->getSettingByGradeLevel(
                $settingData->gradeLevel,
                $settingData->academicYearId
            );

            if ($existingSetting) {
                return $this->validationError([
                    'grade_level' => ['Pengaturan SPP untuk tingkat kelas ini pada tahun akademik yang dipilih sudah ada']
                ], 'Data duplikat ditemukan');
            }

            $setting = $this->sppSettingRepository->createSetting($settingData->toArray());

            DB::commit();

            $setting->load('academicYear');

            return $this->success($setting, 'Pengaturan SPP berhasil dibuat', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->serverError('Gagal membuat pengaturan SPP', $e);
        }
    }

    public function updateSetting(int $id, array $data)
    {
        DB::beginTransaction();
        try {
            // Validasi tipe data terlebih dahulu
            $typeValidation = $this->validateDataTypes($data);
            if ($typeValidation) {
                return $typeValidation;
            }

            // Validasi input required fields
            $validationResult = $this->validateRequiredFields($data);
            if ($validationResult) {
                return $validationResult;
            }

            $setting = $this->sppSettingRepository->getSettingById($id);

            if (!$setting) {
                return $this->notFoundError('Pengaturan SPP tidak ditemukan');
            }

            $settingData = SppSettingData::fromRequest($data);

            // Validasi business logic
            $validationResult = $this->validateSettingData($settingData);
            if ($validationResult['status'] === 'error') {
                return $validationResult;
            }

            // Cek duplikasi (kecuali untuk record yang sama)
            $existingSetting = $this->sppSettingRepository->getSettingByGradeLevel(
                $settingData->gradeLevel,
                $settingData->academicYearId
            );

            if ($existingSetting && $existingSetting->id != $id) {
                return $this->validationError([
                    'grade_level' => ['Pengaturan SPP untuk tingkat kelas ini pada tahun akademik yang dipilih sudah ada']
                ], 'Data duplikat ditemukan');
            }

            $updated = $this->sppSettingRepository->updateSetting($id, $settingData->toArray());

            if (!$updated) {
                return $this->error('Gagal mengupdate pengaturan SPP', null, 500);
            }

            DB::commit();

            $updatedSetting = $this->sppSettingRepository->getSettingById($id);

            return $this->success($updatedSetting, 'Pengaturan SPP berhasil diupdate', 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->serverError('Gagal mengupdate pengaturan SPP', $e);
        }
    }

    public function deleteSetting(int $id)
    {
        DB::beginTransaction();
        try {
            $setting = $this->sppSettingRepository->getSettingById($id);

            if (!$setting) {
                return $this->notFoundError('Pengaturan SPP tidak ditemukan');
            }

            // TODO: Tambahkan validasi jika setting sudah digunakan dalam pembayaran

            $deleted = $this->sppSettingRepository->deleteSetting($id);

            if (!$deleted) {
                return $this->error('Gagal menghapus pengaturan SPP', null, 500);
            }

            DB::commit();

            return $this->success(null, 'Pengaturan SPP berhasil dihapus', 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->serverError('Gagal menghapus pengaturan SPP', $e);
        }
    }

    public function getSettingsByAcademicYear(int $academicYearId)
    {
        try {
            $settings = $this->sppSettingRepository->getSettingsByAcademicYear($academicYearId);
            return $this->success($settings, 'Data pengaturan SPP per tahun akademik berhasil diambil', 200);
        } catch (\Exception $e) {
            return $this->serverError('Gagal mengambil data pengaturan SPP', $e);
        }
    }

    public function getActiveSettings()
    {
        try {
            $settings = $this->sppSettingRepository->getActiveAcademicYearSettings();
            return $this->success($settings, 'Data pengaturan SPP tahun akademik aktif berhasil diambil', 200);
        } catch (\Exception $e) {
            return $this->serverError('Gagal mengambil data pengaturan SPP aktif', $e);
        }
    }

    /**
     * Validasi required fields untuk create dan update
     */
    private function validateRequiredFields(array $data)
    {
        $errors = [];
        $requiredFields = [
            'academic_year_id' => 'Tahun akademik',
            'grade_level' => 'Tingkat kelas',
            'monthly_amount' => 'Jumlah biaya bulanan',
            'due_date' => 'Tanggal jatuh tempo'
        ];

        foreach ($requiredFields as $field => $label) {
            if (!isset($data[$field]) || $data[$field] === '') {
                $errors[$field] = ["{$label} harus diisi"];
            }
        }

        // Validasi late fee fields jika late_fee_enabled true
        if (isset($data['late_fee_enabled']) && $data['late_fee_enabled']) {
            $lateFeeFields = [
                'late_fee_type' => 'Tipe denda',
                'late_fee_amount' => 'Jumlah denda',
                'late_fee_start_day' => 'Tanggal mulai denda'
            ];

            foreach ($lateFeeFields as $field => $label) {
                if (!isset($data[$field]) || $data[$field] === '') {
                    $errors[$field] = ["{$label} harus diisi ketika denda diaktifkan"];
                }
            }
        }

        if (!empty($errors)) {
            return $this->validationError($errors, 'Data yang diinput tidak lengkap');
        }

        return null;
    }

    /**
     * Validasi business logic
     */
    private function validateSettingData(SppSettingData $settingData)
    {
        $errors = [];

        // Validasi tahun akademik
        $academicYear = $this->academicYearRepository->findById($settingData->academicYearId);
        if (!$academicYear) {
            $errors['academic_year_id'] = ['Tahun akademik tidak valid'];
        }

        // Validasi grade level
        if ($settingData->gradeLevel < 7 || $settingData->gradeLevel > 9) {
            $errors['grade_level'] = ['Tingkat kelas harus antara 7 sampai 9'];
        }

        // Validasi monthly amount
        if ($settingData->monthlyAmount <= 0) {
            $errors['monthly_amount'] = ['Jumlah biaya bulanan harus lebih dari 0'];
        }

        // Validasi due date
        if ($settingData->dueDate < 1 || $settingData->dueDate > 31) {
            $errors['due_date'] = ['Tanggal jatuh tempo harus antara 1 sampai 31'];
        }

        // Validasi late fee jika diaktifkan
        if ($settingData->lateFeeEnabled) {
            if (!in_array($settingData->lateFeeType, ['fixed', 'percentage'])) {
                $errors['late_fee_type'] = ['Tipe denda harus fixed atau percentage'];
            }

            if ($settingData->lateFeeAmount <= 0) {
                $errors['late_fee_amount'] = ['Jumlah denda harus lebih dari 0'];
            }

            if ($settingData->lateFeeStartDay < 1 || $settingData->lateFeeStartDay > 31) {
                $errors['late_fee_start_day'] = ['Tanggal mulai denda harus antara 1 sampai 31'];
            }

            if ($settingData->lateFeeStartDay <= $settingData->dueDate) {
                $errors['late_fee_start_day'] = ['Tanggal mulai denda harus setelah tanggal jatuh tempo'];
            }
        }

        if (!empty($errors)) {
            return $this->validationError($errors, 'Validasi data gagal');
        }

        return $this->success(null, 'Validasi berhasil', 200);
    }

    private function validateDataTypes(array $data)
    {
        $errors = [];

        // Validasi academic_year_id
        if (isset($data['academic_year_id']) && !is_numeric($data['academic_year_id'])) {
            $errors['academic_year_id'] = ['Tahun akademik harus berupa angka'];
        }

        // Validasi grade_level
        if (isset($data['grade_level']) && !is_numeric($data['grade_level'])) {
            $errors['grade_level'] = ['Tingkat kelas harus berupa angka'];
        }

        // Validasi monthly_amount
        if (isset($data['monthly_amount']) && !is_numeric($data['monthly_amount'])) {
            $errors['monthly_amount'] = ['Jumlah biaya bulanan harus berupa angka'];
        }

        // Validasi due_date
        if (isset($data['due_date']) && !is_numeric($data['due_date'])) {
            $errors['due_date'] = ['Tanggal jatuh tempo harus berupa angka'];
        }

        // Validasi late_fee_enabled sebagai boolean
        if (isset($data['late_fee_enabled'])) {
            $value = $data['late_fee_enabled'];
            $isValidBoolean = is_bool($value) ||
                            (is_numeric($value) && in_array($value, [0, 1])) ||
                            (is_string($value) && in_array(strtolower($value), ['true', 'false', '1', '0', 'yes', 'no', 'on', 'off']));

            if (!$isValidBoolean) {
                $errors['late_fee_enabled'] = ['Field denda diaktifkan harus berupa boolean (true/false)'];
            }
        }

        // Validasi late_fee_amount
        if (isset($data['late_fee_amount']) && !is_numeric($data['late_fee_amount'])) {
            $errors['late_fee_amount'] = ['Jumlah denda harus berupa angka'];
        }

        // Validasi late_fee_start_day
        if (isset($data['late_fee_start_day']) && !is_numeric($data['late_fee_start_day'])) {
            $errors['late_fee_start_day'] = ['Tanggal mulai denda harus berupa angka'];
        }

        if (!empty($errors)) {
            return $this->validationError($errors, 'Tipe data tidak valid');
        }

        return null;
    }
}
