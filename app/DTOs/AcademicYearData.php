<?php

namespace App\DTOs;

use Carbon\Carbon;

class AcademicYearData
{
    public function __construct(
        public string $name,
        public Carbon $startDate,
        public Carbon $endDate,
        public bool $isActive = false
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            name: $data['name'],
            startDate: Carbon::parse($data['start_date']),
            endDate: Carbon::parse($data['end_date']),
            isActive: $data['is_active'] ?? false
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'start_date' => $this->startDate->format('Y-m-d'),
            'end_date' => $this->endDate->format('Y-m-d'),
            'is_active' => $this->isActive,
        ];
    }
}
