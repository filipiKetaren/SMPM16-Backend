<?php
// app/Exports/SavingsReportExport.php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class SavingsReportExport implements FromCollection, WithHeadings, WithTitle, WithStyles
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function collection()
    {
        $transactions = collect($this->data['transactions'] ?? []);

        return $transactions->map(function ($transaction) {
            return [
                $transaction['transaction_number'],
                $transaction['student_nis'],
                $transaction['student_name'],
                $transaction['class'],
                $transaction['transaction_type'] == 'deposit' ? 'Setoran' : 'Penarikan',
                'Rp ' . number_format($transaction['amount'], 0, ',', '.'),
                'Rp ' . number_format($transaction['balance_before'], 0, ',', '.'),
                'Rp ' . number_format($transaction['balance_after'], 0, ',', '.'),
                $transaction['transaction_date'],
                $transaction['created_by'],
                $transaction['notes'] ?? '-',
            ];
        });
    }

    public function headings(): array
    {
        return [
            'No. Transaksi',
            'NIS',
            'Nama Siswa',
            'Kelas',
            'Jenis Transaksi',
            'Jumlah',
            'Saldo Sebelum',
            'Saldo Sesudah',
            'Tanggal Transaksi',
            'Dibuat Oleh',
            'Catatan',
        ];
    }

    public function title(): string
    {
        return 'Laporan Tabungan';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }
}
