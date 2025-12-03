<?php
// app/Exports/SppReportExport.php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SppReportExport implements FromCollection, WithHeadings, WithTitle, WithStyles
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        $payments = collect($this->data['payments'] ?? []);

        return $payments->map(function ($payment) {
            $monthsPaid = collect($payment['months_paid'] ?? [])
                ->map(fn($month) => "{$month['month']}/{$month['year']}")
                ->implode(', ');

            return [
                $payment['receipt_number'],
                $payment['student_nis'],
                $payment['student_name'],
                $payment['class'],
                $payment['payment_date'],
                $monthsPaid,
                'Rp ' . number_format($payment['total_amount'], 0, ',', '.'),
                $payment['payment_method'],
                $payment['created_by']
            ];
        });
    }

    public function headings(): array
    {
        return [
            'No. Kwitansi',
            'NIS',
            'Nama Siswa',
            'Kelas',
            'Tanggal Bayar',
            'Bulan Dibayar',
            'Jumlah',
            'Metode Bayar',
            'Dibuat Oleh'
        ];
    }

    public function title(): string
    {
        return 'Laporan SPP';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
