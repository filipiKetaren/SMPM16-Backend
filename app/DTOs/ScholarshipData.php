<?php

namespace App\DTOs;

class ScholarshipData
{
    public function __construct(
        public int $studentId,
        public string $scholarshipName,
        public string $type,
        public string $startDate,
        public string $endDate,
        public ?int $academicYearId = null, // Ubah menjadi nullable
        public ?float $discountPercentage = null,
        public ?float $discountAmount = null,
        public ?string $description = null,
        public ?string $sponsor = null,
        public ?string $requirements = null
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            studentId: (int) $data['student_id'],
            scholarshipName: (string) $data['scholarship_name'],
            type: (string) $data['type'],
            startDate: (string) $data['start_date'],
            endDate: (string) $data['end_date'],
            academicYearId: isset($data['academic_year_id']) ? (int) $data['academic_year_id'] : null, // Ubah
            discountPercentage: isset($data['discount_percentage']) ? (float) $data['discount_percentage'] : null,
            discountAmount: isset($data['discount_amount']) ? (float) $data['discount_amount'] : null,
            description: $data['description'] ?? null,
            sponsor: $data['sponsor'] ?? null,
            requirements: $data['requirements'] ?? null
        );
    }

    public function toArray(): array
    {
        return [
            'student_id' => $this->studentId,
            'scholarship_name' => $this->scholarshipName,
            'type' => $this->type,
            'discount_percentage' => $this->discountPercentage,
            'discount_amount' => $this->discountAmount,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'academic_year_id' => $this->academicYearId, // Tetap disimpan jika ada
            'description' => $this->description,
            'sponsor' => $this->sponsor,
            'requirements' => $this->requirements,
            'status' => 'active'
        ];
    }
}
