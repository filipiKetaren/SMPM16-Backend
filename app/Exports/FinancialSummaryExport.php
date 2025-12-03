<?php
// app/Exports/FinancialSummaryExport.php

namespace App\Exports;

use Maatwebsite\Excel\Concerns\FromArray;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class FinancialSummaryExport implements WithMultipleSheets
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function sheets(): array
    {
        $sheets = [];

        // Sheet 1: Ringkasan Keuangan
        $sheets[] = new SummarySheet($this->data);

        // Sheet 2: Laporan SPP
        $sheets[] = new SppSheet($this->data['spp'] ?? []);

        // Sheet 3: Laporan Tabungan
        $sheets[] = new SavingsSheet($this->data['savings'] ?? []);

        return $sheets;
    }
}

class SummarySheet implements FromArray, WithTitle, WithStyles
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function array(): array
    {
        $summary = $this->data['total_finance'] ?? [];
        $sppSummary = $this->data['spp']['summary'] ?? [];
        $savingsSummary = $this->data['savings']['summary'] ?? [];

        return [
            ['LAPORAN RINGKASAN KEUANGAN'],
            ['Periode', $this->data['spp']['filters']['period_type'] == 'monthly' ? 'Bulanan' : 'Tahunan'],
            ['Tahun', $this->data['spp']['filters']['year'] ?? date('Y')],
            ['Bulan', $this->data['spp']['filters']['month'] ?? '-'],
            [],
            ['PENDAPATAN SPP'],
            ['Total Penerimaan SPP', 'Rp ' . number_format($sppSummary['total_income'] ?? 0, 0, ',', '.')],
            ['Total Pembayaran', $sppSummary['total_payments'] ?? 0],
            ['Total Siswa', $sppSummary['total_students'] ?? 0],
            [],
            ['TABUNGAN SISWA'],
            ['Total Setoran', 'Rp ' . number_format($savingsSummary['total_deposits'] ?? 0, 0, ',', '.')],
            ['Total Penarikan', 'Rp ' . number_format($savingsSummary['total_withdrawals'] ?? 0, 0, ',', '.')],
            ['Saldo Saat Ini', 'Rp ' . number_format($savingsSummary['current_balance'] ?? 0, 0, ',', '.')],
            [],
            ['RINGKASAN KEUANGAN'],
            ['Total Penerimaan', 'Rp ' . number_format($summary['total_income'] ?? 0, 0, ',', '.')],
            ['Total Pengeluaran', 'Rp ' . number_format($summary['total_expenditure'] ?? 0, 0, ',', '.')],
            ['Pendapatan Bersih', 'Rp ' . number_format($summary['net_income'] ?? 0, 0, ',', '.')],
            [],
            ['Dibuat pada', $this->data['spp']['generated_at'] ?? now()->format('Y-m-d H:i:s')],
        ];
    }

    public function title(): string
    {
        return 'Ringkasan';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 16]],
            6 => ['font' => ['bold' => true]],
            11 => ['font' => ['bold' => true]],
            16 => ['font' => ['bold' => true]],
        ];
    }
}

class SppSheet implements FromArray, WithTitle, WithStyles
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function array(): array
    {
        $payments = collect($this->data['payments'] ?? []);

        $rows = [
            ['LAPORAN SPP'],
            [],
            ['No. Kwitansi', 'NIS', 'Nama Siswa', 'Kelas', 'Tanggal Bayar', 'Bulan Dibayar', 'Jumlah', 'Metode Bayar', 'Dibuat Oleh']
        ];

        foreach ($payments as $payment) {
            $monthsPaid = collect($payment['months_paid'] ?? [])
                ->map(fn($month) => "{$month['month']}/{$month['year']}")
                ->implode(', ');

            $rows[] = [
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
        }

        return $rows;
    }

    public function title(): string
    {
        return 'SPP';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 16]],
            3 => ['font' => ['bold' => true]],
        ];
    }
}

class SavingsSheet implements FromArray, WithTitle, WithStyles
{
    protected $data;

    public function __construct(array $data)
    {
        $this->data = $data;
    }

    public function array(): array
    {
        $transactions = collect($this->data['transactions'] ?? []);

        $rows = [
            ['LAPORAN TABUNGAN'],
            [],
            ['No. Transaksi', 'NIS', 'Nama Siswa', 'Kelas', 'Jenis', 'Jumlah', 'Saldo Sebelum', 'Saldo Sesudah', 'Tanggal', 'Dibuat Oleh', 'Catatan']
        ];

        foreach ($transactions as $transaction) {
            $rows[] = [
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
        }

        return $rows;
    }

    public function title(): string
    {
        return 'Tabungan';
    }

    public function styles(Worksheet $sheet)
    {
        return [
            1 => ['font' => ['bold' => true, 'size' => 16]],
            3 => ['font' => ['bold' => true]],
        ];
    }
}
