<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan SPP - SMP Muhammadiyah 16 Lubuk Pakam</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 20px; }
        .title { font-size: 16px; font-weight: bold; }
        .subtitle { font-size: 14px; }
        .period { font-size: 12px; }
        table { width: 100%; border-collapse: collapse; margin-top: 20px; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .summary { margin-top: 30px; }
        .summary-item { margin-bottom: 5px; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">LAPORAN PEMBAYARAN SPP</div>
        <div class="subtitle">SMP MUHAMMADIYAH 16 LUBUK PAKAM</div>
        <div class="period">Periode: {{ $periodInfo['display'] }}</div>
        <div>Dibuat: {{ now()->format('d F Y H:i:s') }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>No. Kwitansi</th>
                <th>NIS</th>
                <th>Nama Siswa</th>
                <th>Kelas</th>
                <th>Tanggal Bayar</th>
                <th>Bulan Dibayar</th>
                <th>Jumlah</th>
                <th>Metode Bayar</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['payments'] as $payment)
            <tr>
                <td>{{ $payment['receipt_number'] }}</td>
                <td>{{ $payment['student_nis'] }}</td>
                <td>{{ $payment['student_name'] }}</td>
                <td>{{ $payment['class'] }}</td>
                <td>{{ $payment['payment_date'] }}</td>
                <td>
                    @foreach($payment['months_paid'] as $month)
                        {{ $month['month'] }}/{{ $month['year'] }}
                        @if(!$loop->last), @endif
                    @endforeach
                </td>
                <td class="text-right">Rp {{ number_format($payment['total_amount'], 0, ',', '.') }}</td>
                <td>{{ $payment['payment_method'] }}</td>
            </tr>
            @endforeach
        </tbody>
        <tfoot>
            <tr>
                <td colspan="6" style="text-align: right; font-weight: bold;">TOTAL:</td>
                <td class="text-right" style="font-weight: bold;">Rp {{ number_format($data['summary']['total_income'] ?? 0, 0, ',', '.') }}</td>
                <td></td>
            </tr>
        </tfoot>
    </table>

    <div class="summary">
        <div class="summary-item"><strong>Ringkasan:</strong></div>
        <div class="summary-item">Total Penerimaan: Rp {{ number_format($data['summary']['total_income'] ?? 0, 0, ',', '.') }}</div>
        <div class="summary-item">Total Pembayaran: {{ $data['summary']['total_payments'] ?? 0 }}</div>
        <div class="summary-item">Total Siswa: {{ $data['summary']['total_students'] ?? 0 }}</div>
    </div>
</body>
</html>
