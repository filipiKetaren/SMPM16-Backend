<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Laporan Tabungan - SMP Muhammadiyah 16 Lubuk Pakam</title>
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
        .deposit { color: green; }
        .withdrawal { color: red; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">LAPORAN TABUNGAN SISWA</div>
        <div class="subtitle">SMP MUHAMMADIYAH 16 LUBUK PAKAM</div>
        <div class="period">Periode: {{ $periodInfo['display'] }}</div>
        <div>Dibuat: {{ now()->format('d F Y H:i:s') }}</div>
    </div>

    <table>
        <thead>
            <tr>
                <th>No. Transaksi</th>
                <th>NIS</th>
                <th>Nama Siswa</th>
                <th>Kelas</th>
                <th>Jenis</th>
                <th>Jumlah</th>
                <th>Saldo Sebelum</th>
                <th>Saldo Sesudah</th>
                <th>Tanggal</th>
            </tr>
        </thead>
        <tbody>
            @foreach($data['transactions'] as $transaction)
            <tr>
                <td>{{ $transaction['transaction_number'] }}</td>
                <td>{{ $transaction['student_nis'] }}</td>
                <td>{{ $transaction['student_name'] }}</td>
                <td>{{ $transaction['class'] }}</td>
                <td class="{{ $transaction['transaction_type'] == 'deposit' ? 'deposit' : 'withdrawal' }}">
                    {{ $transaction['transaction_type'] == 'deposit' ? 'Setoran' : 'Penarikan' }}
                </td>
                <td class="text-right">Rp {{ number_format($transaction['amount'], 0, ',', '.') }}</td>
                <td class="text-right">Rp {{ number_format($transaction['balance_before'], 0, ',', '.') }}</td>
                <td class="text-right">Rp {{ number_format($transaction['balance_after'], 0, ',', '.') }}</td>
                <td>{{ $transaction['transaction_date'] }}</td>
            </tr>
            @endforeach
        </tbody>
    </table>

    <div class="summary">
        <div class="summary-item"><strong>Ringkasan Tabungan:</strong></div>
        <div class="summary-item">Total Setoran: Rp {{ number_format($data['summary']['total_deposits'] ?? 0, 0, ',', '.') }}</div>
        <div class="summary-item">Total Penarikan: Rp {{ number_format($data['summary']['total_withdrawals'] ?? 0, 0, ',', '.') }}</div>
        <div class="summary-item">Saldo Saat Ini: Rp {{ number_format($data['summary']['current_balance'] ?? 0, 0, ',', '.') }}</div>
        <div class="summary-item">Aliran Bersih: Rp {{ number_format($data['summary']['net_flow'] ?? 0, 0, ',', '.') }}</div>
        <div class="summary-item">Total Transaksi: {{ $data['summary']['total_transactions'] ?? 0 }}</div>
    </div>
</body>
</html>
