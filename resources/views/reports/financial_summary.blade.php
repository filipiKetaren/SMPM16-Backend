<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <title>Ringkasan Keuangan - SMP Muhammadiyah 16 Lubuk Pakam</title>
    <style>
        body { font-family: Arial, sans-serif; font-size: 12px; }
        .header { text-align: center; margin-bottom: 30px; }
        .title { font-size: 18px; font-weight: bold; }
        .subtitle { font-size: 14px; }
        .period { font-size: 12px; }
        .section { margin-bottom: 30px; }
        .section-title { font-size: 14px; font-weight: bold; margin-bottom: 10px; border-bottom: 2px solid #000; padding-bottom: 5px; }
        table { width: 100%; border-collapse: collapse; margin-bottom: 15px; }
        th, td { border: 1px solid #000; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        .text-right { text-align: right; }
        .text-center { text-align: center; }
        .summary-box { border: 2px solid #000; padding: 15px; margin-top: 20px; background-color: #f9f9f9; }
        .summary-total { font-size: 14px; font-weight: bold; margin-top: 10px; }
        .positive { color: green; }
        .negative { color: red; }
    </style>
</head>
<body>
    <div class="header">
        <div class="title">LAPORAN RINGKASAN KEUANGAN</div>
        <div class="subtitle">SMP MUHAMMADIYAH 16 LUBUK PAKAM</div>
        <div class="period">Periode: {{ $periodInfo['display'] }}</div>
        <div>Dibuat: {{ now()->format('d F Y H:i:s') }}</div>
    </div>

    <div class="section">
        <div class="section-title">1. PENDAPATAN SPP</div>
        <table>
            <thead>
                <tr>
                    <th>No. Kwitansi</th>
                    <th>Nama Siswa</th>
                    <th>Kelas</th>
                    <th>Tanggal Bayar</th>
                    <th>Jumlah</th>
                    <th>Metode Bayar</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['spp']['payments'] as $payment)
                <tr>
                    <td>{{ $payment['receipt_number'] }}</td>
                    <td>{{ $payment['student_name'] }}</td>
                    <td>{{ $payment['class'] }}</td>
                    <td>{{ $payment['payment_date'] }}</td>
                    <td class="text-right">Rp {{ number_format($payment['total_amount'], 0, ',', '.') }}</td>
                    <td>{{ $payment['payment_method'] }}</td>
                </tr>
                @endforeach
            </tbody>
            <tfoot>
                <tr>
                    <td colspan="4" style="text-align: right; font-weight: bold;">Total SPP:</td>
                    <td class="text-right" style="font-weight: bold;">Rp {{ number_format($data['spp']['summary']['total_income'] ?? 0, 0, ',', '.') }}</td>
                    <td></td>
                </tr>
            </tfoot>
        </table>
        <div><strong>Ringkasan SPP:</strong> {{ $data['spp']['summary']['total_payments'] ?? 0 }} pembayaran dari {{ $data['spp']['summary']['total_students'] ?? 0 }} siswa</div>
    </div>

    <div class="section">
        <div class="section-title">2. TABUNGAN SISWA</div>
        <table>
            <thead>
                <tr>
                    <th>No. Transaksi</th>
                    <th>Nama Siswa</th>
                    <th>Kelas</th>
                    <th>Jenis</th>
                    <th>Jumlah</th>
                    <th>Saldo Sesudah</th>
                    <th>Tanggal</th>
                </tr>
            </thead>
            <tbody>
                @foreach($data['savings']['transactions'] as $transaction)
                <tr>
                    <td>{{ $transaction['transaction_number'] }}</td>
                    <td>{{ $transaction['student_name'] }}</td>
                    <td>{{ $transaction['class'] }}</td>
                    <td class="{{ $transaction['transaction_type'] == 'deposit' ? 'positive' : 'negative' }}">
                        {{ $transaction['transaction_type'] == 'deposit' ? 'Setoran' : 'Penarikan' }}
                    </td>
                    <td class="text-right">Rp {{ number_format($transaction['amount'], 0, ',', '.') }}</td>
                    <td class="text-right">Rp {{ number_format($transaction['balance_after'], 0, ',', '.') }}</td>
                    <td>{{ $transaction['transaction_date'] }}</td>
                </tr>
                @endforeach
            </tbody>
        </table>
        <div><strong>Ringkasan Tabungan:</strong> Setoran: Rp {{ number_format($data['savings']['summary']['total_deposits'] ?? 0, 0, ',', '.') }} | Penarikan: Rp {{ number_format($data['savings']['summary']['total_withdrawals'] ?? 0, 0, ',', '.') }}</div>
    </div>

    <div class="summary-box">
        <div class="section-title">RINGKASAN KEUANGAN TOTAL</div>
        <table>
            <tr>
                <td><strong>Total Penerimaan SPP:</strong></td>
                <td class="text-right">Rp {{ number_format($data['spp']['summary']['total_income'] ?? 0, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td><strong>Total Setoran Tabungan:</strong></td>
                <td class="text-right">Rp {{ number_format($data['savings']['summary']['total_deposits'] ?? 0, 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td><strong>Total Pendapatan:</strong></td>
                <td class="text-right positive">Rp {{ number_format(($data['spp']['summary']['total_income'] ?? 0) + ($data['savings']['summary']['total_deposits'] ?? 0), 0, ',', '.') }}</td>
            </tr>
            <tr>
                <td><strong>Total Pengeluaran (Penarikan Tabungan):</strong></td>
                <td class="text-right negative">Rp {{ number_format($data['savings']['summary']['total_withdrawals'] ?? 0, 0, ',', '.') }}</td>
            </tr>
            <tr style="border-top: 2px solid #000;">
                <td><strong>PENDAPATAN BERSIH:</strong></td>
                <td class="text-right">
                    @php
                        $netIncome = (($data['spp']['summary']['total_income'] ?? 0) + ($data['savings']['summary']['total_deposits'] ?? 0)) - ($data['savings']['summary']['total_withdrawals'] ?? 0);
                    @endphp
                    <span class="{{ $netIncome >= 0 ? 'positive' : 'negative' }}">
                        Rp {{ number_format($netIncome, 0, ',', '.') }}
                    </span>
                </td>
            </tr>
        </table>
    </div>
</body>
</html>
