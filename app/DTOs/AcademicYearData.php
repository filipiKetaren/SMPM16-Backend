<?php

namespace App\DTOs;

class AcademicYearData
{
    public function __construct(
        public string $name,
        public string $startDate,
        public string $endDate,
        public bool $isActive = false
    ) {}

    public static function fromRequest(array $data): self
    {
        return new self(
            name: $data['name'],
            startDate: $data['start_date'],
            endDate: $data['end_date'],
            isActive: $data['is_active'] ?? false
        );
    }

    public function toArray(): array
    {
        return [
            'name' => $this->name,
            'start_date' => $this->startDate,
            'end_date' => $this->endDate,
            'is_active' => $this->isActive,
        ];
    }
}
