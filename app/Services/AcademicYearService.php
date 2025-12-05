<?php

namespace App\Services;

use App\DTOs\AcademicYearData;
use App\Repositories\Interfaces\AcademicYearRepositoryInterface;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;

class AcademicYearService extends BaseService
{
    public function __construct(
        private AcademicYearRepositoryInterface $academicYearRepository
    ) {}

    public function getAllAcademicYears(array $filters = [])
    {
        try {
            // Get pagination parameters
            $perPage = isset($filters['per_page']) ? (int) $filters['per_page'] : 5;
            $page = isset($filters['page']) ? (int) $filters['page'] : 1;

            // Remove pagination parameters from filters
            unset($filters['per_page'], $filters['page']);

            // Get paginated academic years
            $academicYearsPaginator = $this->academicYearRepository
                ->getAllPaginated($filters, $perPage);

            return $this->success($academicYearsPaginator, 'Data tahun akademik berhasil diambil', 200);

        } catch (\Exception $e) {
            return $this->serverError('Gagal mengambil data tahun akademik', $e);
        }
    }

    public function getAcademicYearById(int $id)
    {
        try {
            $academicYear = $this->academicYearRepository->findById($id);

            if (!$academicYear) {
                return $this->notFoundError('Tahun akademik tidak ditemukan');
            }

            return $this->success($academicYear, 'Detail tahun akademik berhasil diambil', 200);
        } catch (\Exception $e) {
            return $this->serverError('Gagal mengambil detail tahun akademik', $e);
        }
    }

    public function createAcademicYear(array $data)
    {
        DB::beginTransaction();
        try {
            // Validasi input
            $validationResult = $this->validateAcademicYearData($data);
            if ($validationResult) {
                return $validationResult;
            }

            $academicYearData = AcademicYearData::fromRequest($data);

            // Auto-activate jika tidak ada tahun akademik aktif
            $activeAcademicYear = $this->academicYearRepository->getActiveAcademicYear();

            if (!$activeAcademicYear && !$academicYearData->isActive) {
                // Tidak ada tahun aktif, dan tahun baru tidak di-set aktif
                // Otomatis aktifkan tahun baru ini
                $academicYearData->isActive = true;
            }

            // Jika tahun akademik di set aktif, maka nonaktifkan yang lain
            if ($academicYearData->isActive) {
                $this->academicYearRepository->deactivateAll();
            }

            $academicYear = $this->academicYearRepository->create($academicYearData->toArray());

            DB::commit();

            return $this->success($academicYear, 'Tahun akademik berhasil dibuat', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->serverError('Gagal membuat tahun akademik', $e);
        }
    }

    public function updateAcademicYear(int $id, array $data)
    {
        DB::beginTransaction();
        try {
            $academicYear = $this->academicYearRepository->findById($id);

            if (!$academicYear) {
                return $this->notFoundError('Tahun akademik tidak ditemukan');
            }

            // Validasi input
            $validationResult = $this->validateAcademicYearData($data, $id);
            if ($validationResult) {
                return $validationResult;
            }

            $academicYearData = AcademicYearData::fromRequest($data);

            // ✅ PERBAIKAN: Gunakan repository method
            // Cek jika mencoba menonaktifkan tahun akademik
            if (isset($data['is_active']) && $data['is_active'] == false) {
                // Jika tahun akademik saat ini aktif
                if ($academicYear->is_active) {
                    // ✅ Gunakan repository untuk validasi
                    $activeCount = $this->academicYearRepository->countActiveAcademicYears($id);

                    if ($activeCount === 0) {
                        return $this->error(
                            'Tidak dapat menonaktifkan tahun akademik karena ini adalah satu-satunya tahun akademik aktif. Sistem memerlukan minimal satu tahun akademik aktif.',
                            [
                                'current_active_count' => 0,
                                'minimum_required' => 1,
                                'solution' => 'Aktifkan minimal satu tahun akademik lain sebelum menonaktifkan ini'
                            ],
                            422
                        );
                    }
                }
            }

            // ✅ PERBAIKAN: Hanya deactivateAll jika mengaktifkan tahun akademik baru
            if ($academicYearData->isActive && !$academicYear->is_active) {
                $this->academicYearRepository->deactivateAll();
            }

            $updated = $this->academicYearRepository->update($id, $academicYearData->toArray());

            if (!$updated) {
                return $this->error('Gagal mengupdate tahun akademik', null, 500);
            }

            DB::commit();

            $updatedAcademicYear = $this->academicYearRepository->findById($id);

            return $this->success($updatedAcademicYear, 'Tahun akademik berhasil diupdate', 200);

        } catch (\Exception $e) {
            DB::rollBack();
            return $this->serverError('Gagal mengupdate tahun akademik', $e);
        }
    }

    public function deleteAcademicYear(int $id)
    {
        DB::beginTransaction();
        try {
            $academicYear = $this->academicYearRepository->findById($id);

            if (!$academicYear) {
                return $this->notFoundError('Tahun akademik tidak ditemukan');
            }

            // ✅ PERBAIKAN: Gunakan repository untuk validasi
            if ($academicYear->is_active) {
                $activeCount = $this->academicYearRepository->countActiveAcademicYears($id);

                if ($activeCount === 0) {
                    return $this->error(
                        'Tidak dapat menghapus tahun akademik aktif karena ini adalah satu-satunya tahun akademik aktif',
                        [
                            'academic_year' => [
                                'id' => $academicYear->id,
                                'name' => $academicYear->name,
                                'is_active' => true
                            ],
                            'solution' => 'Nonaktifkan tahun akademik ini terlebih dahulu, atau aktifkan tahun akademik lain sebelum menghapus'
                        ],
                        422
                    );
                }
            }

            // Cek apakah tahun akademik sedang digunakan
            if ($this->isAcademicYearInUse($academicYear)) {
                return $this->validationError([
                    'academic_year' => ['Tahun akademik tidak dapat dihapus karena sedang digunakan']
                ], 'Tahun akademik sedang digunakan');
            }

            $deleted = $this->academicYearRepository->delete($id);

            if (!$deleted) {
                return $this->error('Gagal menghapus tahun akademik', null, 500);
            }

            DB::commit();

            return $this->success(null, 'Tahun akademik berhasil dihapus', 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->serverError('Gagal menghapus tahun akademik', $e);
        }
    }

    public function getActiveAcademicYear()
    {
        try {
            $academicYear = $this->academicYearRepository->getActiveAcademicYear();
            return $this->success($academicYear, 'Tahun akademik aktif berhasil diambil', 200);
        } catch (\Exception $e) {
            return $this->serverError('Gagal mengambil tahun akademik aktif', $e);
        }
    }

    /**
     * Validasi data tahun akademik
     */
    private function validateAcademicYearData(array $data, int $id = null)
    {
        $errors = [];

        // Validasi required fields
        $requiredFields = [
            'name' => 'Nama tahun akademik',
            'start_date' => 'Tanggal mulai',
            'end_date' => 'Tanggal selesai'
        ];

        foreach ($requiredFields as $field => $label) {
            if (empty($data[$field])) {
                $errors[$field] = ["{$label} harus diisi"];
            }
        }

        // Validasi format tanggal
        if (isset($data['start_date']) && !empty($data['start_date'])) {
            if (!$this->isValidDate($data['start_date'])) {
                $errors['start_date'] = ['Format tanggal mulai tidak valid'];
            }
        }

        if (isset($data['end_date']) && !empty($data['end_date'])) {
            if (!$this->isValidDate($data['end_date'])) {
                $errors['end_date'] = ['Format tanggal selesai tidak valid'];
            }
        }

        // Validasi logika tanggal hanya jika kedua tanggal valid
        if (isset($data['start_date']) && isset($data['end_date']) &&
            !isset($errors['start_date']) && !isset($errors['end_date'])) {

            try {
                $startDate = \Carbon\Carbon::parse($data['start_date']);
                $endDate = \Carbon\Carbon::parse($data['end_date']);

                // **PERBAIKAN: Validasi urutan tanggal DULU**
                if ($startDate->gte($endDate)) {
                    $errors['end_date'] = ['Tanggal selesai harus setelah tanggal mulai'];
                }
                // **Hanya validasi durasi jika urutan tanggal sudah benar**
                else if ($startDate->diffInMonths($endDate) < 1) {
                    $errors['end_date'] = ['Tahun akademik harus minimal 1 bulan'];
                }

            } catch (\Exception $e) {
                $errors['start_date'] = ['Terjadi kesalahan dalam memproses tanggal'];
            }
        }

        // Validasi unik name (kecuali untuk id yang sama)
        if (isset($data['name']) && !empty($data['name'])) {
            $existing = \App\Models\AcademicYear::where('name', $data['name'])
                ->when($id, function ($query) use ($id) {
                    return $query->where('id', '!=', $id);
                })
                ->exists();

            if ($existing) {
                $errors['name'] = ['Nama tahun akademik sudah digunakan'];
            }
        }

        if (!empty($errors)) {
            return $this->validationError($errors, 'Validasi data gagal');
        }

        return null;
    }

    /**
     * Validasi format tanggal
     */
    private function isValidDate(string $date): bool
    {
        try {
            \Carbon\Carbon::parse($date);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Cek apakah tahun akademik sedang digunakan
     */
    private function isAcademicYearInUse($academicYear): bool
    {
        // Cek di classes
        if ($academicYear->classes()->exists()) {
            return true;
        }

        // Cek di spp_settings
        if ($academicYear->sppSettings()->exists()) {
            return true;
        }

        // Tambahkan pengecekan lain jika diperlukan

        return false;
    }
}
