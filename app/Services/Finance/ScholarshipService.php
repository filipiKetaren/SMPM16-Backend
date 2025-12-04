<?php

namespace App\Services\Finance;

use App\DTOs\ScholarshipData;
use App\Repositories\Interfaces\ScholarshipRepositoryInterface;
use App\Repositories\Interfaces\StudentRepositoryInterface;
use App\Repositories\Interfaces\AcademicYearRepositoryInterface;
use App\Services\BaseService;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class ScholarshipService extends BaseService
{
    public function __construct(
        private ScholarshipRepositoryInterface $scholarshipRepository,
        private StudentRepositoryInterface $studentRepository,
        private AcademicYearRepositoryInterface $academicYearRepository
    ) {}

    public function getAllScholarships(array $filters = [])
    {
        try {
            $scholarships = $this->scholarshipRepository->getAllWithStudent($filters);
            return $this->success($scholarships, 'Data beasiswa berhasil diambil', 200);
        } catch (\Exception $e) {
            return $this->serverError('Gagal mengambil data beasiswa', $e);
        }
    }

    public function createScholarship(array $data)
    {
        DB::beginTransaction();
        try {
            // Validasi required fields dengan pesan yang jelas
            $validationResult = $this->validateScholarshipData($data);
            if ($validationResult) {
                return $validationResult;
            }

            $scholarshipData = ScholarshipData::fromRequest($data);

            // Validasi: Cek apakah siswa sudah memiliki beasiswa aktif dengan nama yang sama
            $duplicateValidation = $this->validateDuplicateScholarship($scholarshipData);
            if ($duplicateValidation) {
                return $duplicateValidation;
            }

            // **PERUBAHAN: Validasi tahun akademik hanya jika ada academic_year_id**
            if ($scholarshipData->academicYearId) {
                $academicYearValidation = $this->validateScholarshipAcademicYear($scholarshipData);
                if ($academicYearValidation) {
                    return $academicYearValidation;
                }
            }

            // Validasi tanggal tumpang tindih
            $overlapValidation = $this->validateScholarshipDateOverlap($scholarshipData);
            if ($overlapValidation) {
                return $overlapValidation;
            }

            // Deactivate all active scholarships for this student (hanya untuk tipe yang sama)
            $this->deactivateSimilarScholarships($scholarshipData);

            $scholarship = $this->scholarshipRepository->create($scholarshipData->toArray());

            DB::commit();

            $scholarship->load('student');

            return $this->success($scholarship, 'Beasiswa berhasil ditambahkan', 201);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->serverError('Gagal menambahkan beasiswa', $e);
        }
    }

    public function getScholarshipDetail($id)
    {
        try {
            $scholarship = $this->scholarshipRepository->findWithStudent($id);

            if (!$scholarship) {
                return $this->notFoundError('Beasiswa tidak ditemukan');
            }

            return $this->success($scholarship, 'Detail beasiswa berhasil diambil', 200);
        } catch (\Exception $e) {
            return $this->serverError('Gagal mengambil detail beasiswa', $e);
        }
    }

    public function updateScholarship($id, array $data)
    {
        DB::beginTransaction();
        try {
            $scholarship = $this->scholarshipRepository->find($id);

            if (!$scholarship) {
                return $this->notFoundError('Beasiswa tidak ditemukan');
            }

            // Validasi required fields
            $validationResult = $this->validateScholarshipData($data, true);
            if ($validationResult) {
                return $validationResult;
            }

            $scholarshipData = ScholarshipData::fromRequest($data);

            // Validasi duplikasi (kecuali yang sedang diupdate)
            $duplicateValidation = $this->validateDuplicateScholarship($scholarshipData, $id);
            if ($duplicateValidation) {
                return $duplicateValidation;
            }

            // **PERUBAHAN: Validasi tahun akademik hanya jika ada academic_year_id**
            if ($scholarshipData->academicYearId) {
                $academicYearValidation = $this->validateScholarshipAcademicYear($scholarshipData, $id);
                if ($academicYearValidation) {
                    return $academicYearValidation;
                }
            }

            // Validasi tanggal tumpang tindih (kecuali yang sedang diupdate)
            $overlapValidation = $this->validateScholarshipDateOverlap($scholarshipData, $id);
            if ($overlapValidation) {
                return $overlapValidation;
            }

            $updated = $this->scholarshipRepository->update($id, $scholarshipData->toArray());

            if (!$updated) {
                return $this->error('Gagal mengupdate beasiswa', null, 500);
            }

            DB::commit();

            $updatedScholarship = $this->scholarshipRepository->findWithStudent($id);

            return $this->success($updatedScholarship, 'Beasiswa berhasil diupdate', 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->serverError('Gagal mengupdate beasiswa', $e);
        }
    }

    public function deleteScholarship($id)
    {
        DB::beginTransaction();
        try {
            $scholarship = $this->scholarshipRepository->find($id);

            if (!$scholarship) {
                return $this->notFoundError('Beasiswa tidak ditemukan');
            }

            $deleted = $this->scholarshipRepository->delete($id);

            if (!$deleted) {
                return $this->error('Gagal menghapus beasiswa', null, 500);
            }

            DB::commit();

            return $this->success(null, 'Beasiswa berhasil dihapus', 200);
        } catch (\Exception $e) {
            DB::rollBack();
            return $this->serverError('Gagal menghapus beasiswa', $e);
        }
    }

    public function getScholarshipsByStudent($studentId)
    {
        try {
            $student = $this->studentRepository->getStudentById($studentId);

            if (!$student) {
                return $this->notFoundError('Siswa tidak ditemukan');
            }

            $scholarships = $this->scholarshipRepository->getByStudent($studentId);

            // Cek jika siswa tidak memiliki beasiswa
            if ($scholarships->isEmpty()) {
                return $this->success([
                    'student' => [
                        'id' => $student->id,
                        'nis' => $student->nis,
                        'full_name' => $student->full_name,
                        'class' => $student->class ? [
                            'id' => $student->class->id,
                            'name' => $student->class->name
                        ] : null
                    ],
                    'scholarships' => [],
                    'has_active_scholarship' => false,
                    'active_scholarship' => null,
                    'message' => 'Siswa tidak memiliki beasiswa'
                ], 'Siswa tidak memiliki beasiswa', 200);
            }

            return $this->success([
                'student' => $student,
                'scholarships' => $scholarships,
                'has_active_scholarship' => $student->hasActiveScholarship(),
                'active_scholarship' => $student->getActiveScholarship()
            ], 'Data beasiswa siswa berhasil diambil', 200);
        } catch (\Exception $e) {
            return $this->serverError('Gagal mengambil data beasiswa siswa', $e);
        }
    }

    public function getScholarshipSummary()
    {
        try {
            $summary = $this->scholarshipRepository->getSummary();

            $recentScholarships = $this->scholarshipRepository->getRecentScholarships(10);

            return $this->success([
                'summary' => $summary,
                'recent_scholarships' => $recentScholarships
            ], 'Ringkasan beasiswa berhasil diambil', 200);
        } catch (\Exception $e) {
            return $this->serverError('Gagal mengambil ringkasan beasiswa', $e);
        }
    }

    /**
     * Validasi duplikasi beasiswa
     */
    private function validateDuplicateScholarship(ScholarshipData $scholarshipData, ?int $excludeId = null)
    {
        try {
            // Cek apakah siswa sudah memiliki beasiswa dengan nama yang sama dalam periode yang sama
            $existingScholarships = $this->scholarshipRepository->getByStudent($scholarshipData->studentId);

            foreach ($existingScholarships as $scholarship) {
                if ($excludeId && $scholarship->id == $excludeId) {
                    continue;
                }

                // Jika nama beasiswa sama dan periode tanggal tumpang tindih
                if ($scholarship->scholarship_name === $scholarshipData->scholarshipName) {
                    $existingStart = Carbon::parse($scholarship->start_date);
                    $existingEnd = Carbon::parse($scholarship->end_date);
                    $newStart = Carbon::parse($scholarshipData->startDate);
                    $newEnd = Carbon::parse($scholarshipData->endDate);

                    // Cek apakah tanggal tumpang tindih
                    if ($newStart->between($existingStart, $existingEnd) ||
                        $newEnd->between($existingStart, $existingEnd) ||
                        $existingStart->between($newStart, $newEnd)) {

                        return $this->error(
                            'Siswa sudah memiliki beasiswa dengan nama yang sama dalam periode tanggal yang tumpang tindih',
                            [
                                'student_id' => $scholarshipData->studentId,
                                'scholarship_name' => $scholarshipData->scholarshipName,
                                'existing_scholarship' => [
                                    'id' => $scholarship->id,
                                    'start_date' => $scholarship->start_date->format('Y-m-d'),
                                    'end_date' => $scholarship->end_date->format('Y-m-d'),
                                    'type' => $scholarship->type
                                ],
                                'new_scholarship' => [
                                    'start_date' => $scholarshipData->startDate,
                                    'end_date' => $scholarshipData->endDate,
                                    'type' => $scholarshipData->type
                                ]
                            ],
                            422
                        );
                    }
                }
            }

            return null;
        } catch (\Exception $e) {
            return $this->serverError('Gagal validasi duplikasi beasiswa', $e);
        }
    }

    /**
     * Nonaktifkan beasiswa serupa untuk siswa yang sama
     */
    private function deactivateSimilarScholarships(ScholarshipData $scholarshipData)
    {
        // Hanya nonaktifkan jika ada beasiswa aktif dengan tipe yang sama
        $activeScholarships = $this->scholarshipRepository->getActiveScholarshipsByStudent(
            $scholarshipData->studentId
        );

        // Filter hanya yang memiliki tipe yang sama
        $similarScholarships = $activeScholarships->filter(function($scholarship) use ($scholarshipData) {
            return $scholarship->type === $scholarshipData->type;
        });

        if (!$similarScholarships->isEmpty()) {
            $this->scholarshipRepository->deactivateStudentScholarships($scholarshipData->studentId);
        }
    }

    /**
     * Validasi data beasiswa dengan validasi key request yang jelas
     */
    private function validateScholarshipData(array $data, bool $isUpdate = false)
    {
        $errors = [];

        // Daftar field yang diperlukan dengan deskripsi untuk frontend
        $requiredFields = [
            'student_id' => [
                'label' => 'Siswa',
                'type' => 'integer',
                'description' => 'ID siswa penerima beasiswa'
            ],
            'scholarship_name' => [
                'label' => 'Nama beasiswa',
                'type' => 'string',
                'description' => 'Nama beasiswa/sponsor'
            ],
            'type' => [
                'label' => 'Jenis beasiswa',
                'type' => 'string',
                'description' => 'Jenis beasiswa: full (penuh) atau partial (parsial)',
                'allowed_values' => ['full', 'partial']
            ],
            'start_date' => [
                'label' => 'Tanggal mulai',
                'type' => 'date',
                'description' => 'Tanggal mulai beasiswa (format: YYYY-MM-DD)'
            ],
            'end_date' => [
                'label' => 'Tanggal berakhir',
                'type' => 'date',
                'description' => 'Tanggal berakhir beasiswa (format: YYYY-MM-DD)'
            ],
        ];

        // Validasi required fields
        foreach ($requiredFields as $field => $fieldInfo) {
            $isRequired = $fieldInfo['required'] ?? true;

            if ($isRequired && (!isset($data[$field]) || $data[$field] === '')) {
                $errors[$field] = ["{$fieldInfo['label']} harus diisi"];
            }

            // Validasi tipe data
            if (isset($data[$field]) && $data[$field] !== '') {
                switch ($fieldInfo['type']) {
                    case 'integer':
                        if (!is_numeric($data[$field])) {
                            $errors[$field] = ["{$fieldInfo['label']} harus berupa angka"];
                        }
                        break;
                    case 'string':
                        if (!is_string($data[$field])) {
                            $errors[$field] = ["{$fieldInfo['label']} harus berupa teks"];
                        }
                        break;
                    case 'date':
                        if (!strtotime($data[$field])) {
                            $errors[$field] = ["Format {$fieldInfo['label']} tidak valid. Gunakan format YYYY-MM-DD"];
                        }
                        break;
                }

                // Validasi nilai yang diizinkan
                if (isset($fieldInfo['allowed_values'])) {
                    if (!in_array($data[$field], $fieldInfo['allowed_values'])) {
                        $errors[$field] = [
                            "{$fieldInfo['label']} harus salah satu dari: " .
                            implode(', ', $fieldInfo['allowed_values'])
                        ];
                    }
                }
            }
        }

        // Validasi type - tambahan validasi jika type diisi tapi nilainya salah
        if (isset($data['type'])) {
            if (!in_array($data['type'], ['full', 'partial'])) {
                $errors['type'] = ['Jenis beasiswa harus full atau partial'];
            }
        }

        // Validasi tanggal
        if (isset($data['start_date']) && isset($data['end_date'])) {
            $startDate = Carbon::parse($data['start_date']);
            $endDate = Carbon::parse($data['end_date']);

            if ($endDate->lte($startDate)) {
                $errors['end_date'] = ['Tanggal berakhir harus setelah tanggal mulai'];
            }

            // **OPTIONAL: Hapus atau comment validasi ini jika ingin mengizinkan tanggal masa lalu**
            // if ($startDate->lt(now())) {
            //     $errors['start_date'] = ['Tanggal mulai tidak boleh kurang dari hari ini'];
            // }
        }

        // Validasi discount untuk partial
        if (isset($data['type']) && $data['type'] === 'partial') {
            $hasDiscount = isset($data['discount_percentage']) && $data['discount_percentage'] > 0 ||
                          isset($data['discount_amount']) && $data['discount_amount'] > 0;

            if (!$hasDiscount) {
                $errors['discount'] = ['Untuk beasiswa parsial, salah satu dari discount_percentage atau discount_amount harus diisi'];
            }

            if (isset($data['discount_percentage']) && $data['discount_percentage'] > 100) {
                $errors['discount_percentage'] = ['Persentase diskon tidak boleh lebih dari 100%'];
            }

            // Validasi tipe data untuk discount
            if (isset($data['discount_percentage']) && !is_numeric($data['discount_percentage'])) {
                $errors['discount_percentage'] = ['Persentase diskon harus berupa angka'];
            }

            if (isset($data['discount_amount']) && !is_numeric($data['discount_amount'])) {
                $errors['discount_amount'] = ['Jumlah diskon harus berupa angka'];
            }
        }

        // Validasi untuk field opsional
        $optionalFields = [
            'description' => 'string',
            'sponsor' => 'string',
            'requirements' => 'string',
            'discount_percentage' => 'numeric',
            'discount_amount' => 'numeric',
            'academic_year_id' => 'integer' // Tambahkan sebagai optional
        ];

        foreach ($optionalFields as $field => $type) {
            if (isset($data[$field]) && $data[$field] !== '') {
                if ($type === 'numeric' && !is_numeric($data[$field])) {
                    $errors[$field] = ["{$field} harus berupa angka"];
                } elseif ($type === 'integer' && !is_numeric($data[$field])) {
                    $errors[$field] = ["{$field} harus berupa angka"];
                }
            }
        }

        if (!empty($errors)) {
            // Tambahkan informasi field yang diperlukan untuk frontend
            $fieldInfo = [];
            foreach ($requiredFields as $field => $info) {
                $fieldInfo[$field] = [
                    'required' => $info['required'] ?? true,
                    'type' => $info['type'],
                    'description' => $info['description'],
                    'allowed_values' => $info['allowed_values'] ?? null
                ];
            }

            // Tambahkan field optional
            foreach ($optionalFields as $field => $type) {
                $fieldInfo[$field] = [
                    'required' => false,
                    'type' => $type,
                    'description' => 'Field opsional'
                ];
            }

            return $this->validationError(
                $errors,
                'Data beasiswa tidak valid',
                ['required_fields' => $fieldInfo]
            );
        }

        return null;
    }

    /**
     * Validasi tahun akademik beasiswa
     */
    private function validateScholarshipAcademicYear(ScholarshipData $scholarshipData, ?int $excludeId = null)
    {
        try {
            // Jika academic_year_id tidak diisi, skip validasi
            if (!$scholarshipData->academicYearId) {
                return null;
            }

            // Dapatkan data siswa
            $student = $this->studentRepository->getStudentById($scholarshipData->studentId);

            if (!$student) {
                return $this->notFoundError('Siswa tidak ditemukan');
            }

            // Dapatkan tahun akademik yang dipilih
            $academicYear = $this->academicYearRepository->findById($scholarshipData->academicYearId);

            if (!$academicYear) {
                return $this->notFoundError('Tahun akademik tidak ditemukan');
            }

            // Validasi tanggal beasiswa sesuai dengan tahun akademik
            $startDate = Carbon::parse($scholarshipData->startDate);
            $endDate = Carbon::parse($scholarshipData->endDate);
            $academicStart = Carbon::parse($academicYear->start_date);
            $academicEnd = Carbon::parse($academicYear->end_date);

            if ($startDate->lt($academicStart) || $endDate->gt($academicEnd)) {
                return $this->error(
                    'Tanggal beasiswa harus berada dalam rentang tahun akademik ' . $academicYear->name,
                    [
                        'academic_year' => [
                            'id' => $academicYear->id,
                            'name' => $academicYear->name,
                            'start_date' => $academicYear->start_date->format('Y-m-d'),
                            'end_date' => $academicYear->end_date->format('Y-m-d')
                        ],
                        'scholarship_dates' => [
                            'start_date' => $scholarshipData->startDate,
                            'end_date' => $scholarshipData->endDate
                        ]
                    ],
                    422
                );
            }

            // Validasi siswa berada di kelas dengan tahun akademik yang sama (opsional)
            if ($student->class && $student->class->academic_year_id != $scholarshipData->academicYearId) {
                // Hanya warning, tidak error karena mungkin sudah pindah kelas
                // Bisa tambahkan logging di sini
            }

            return null;
        } catch (\Exception $e) {
            return $this->serverError('Gagal validasi tahun akademik', $e);
        }
    }

    /**
     * Validasi tanggal beasiswa tidak tumpang tindih
     */
    private function validateScholarshipDateOverlap(ScholarshipData $scholarshipData, ?int $excludeId = null)
    {
        try {
            $hasOverlap = $this->scholarshipRepository->checkDateOverlap(
                $scholarshipData->studentId,
                $scholarshipData->startDate,
                $scholarshipData->endDate,
                $excludeId
            );

            if ($hasOverlap) {
                return $this->error(
                    'Sudah ada beasiswa aktif dalam rentang tanggal yang sama',
                    [
                        'student_id' => $scholarshipData->studentId,
                        'start_date' => $scholarshipData->startDate,
                        'end_date' => $scholarshipData->endDate
                    ],
                    422
                );
            }

            return null;
        } catch (\Exception $e) {
            return $this->serverError('Gagal validasi tanggal beasiswa', $e);
        }
    }
}
