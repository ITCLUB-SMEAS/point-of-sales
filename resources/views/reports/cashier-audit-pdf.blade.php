<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Audit Kasir {{ $report['date'] }}</title>
    <style>
        body { color: #18181b; font-family: DejaVu Sans, sans-serif; font-size: 12px; line-height: 1.45; }
        h1 { font-size: 22px; margin: 0 0 4px; }
        h2 { border-bottom: 1px solid #d4d4d8; font-size: 15px; margin: 22px 0 8px; padding-bottom: 5px; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #d4d4d8; padding: 7px; vertical-align: top; }
        th { background: #f4f4f5; font-weight: 700; text-align: left; }
        .right { text-align: right; }
        .muted { color: #71717a; }
    </style>
</head>
<body>
    @php
        $formatMoney = fn (?int $amount): string => (($amount ?? 0) < 0 ? '-Rp' : 'Rp') . number_format(abs($amount ?? 0), 0, ',', '.');
    @endphp

    <h1>Audit Kasir</h1>
    <div class="muted">Tanggal: {{ $report['date'] }} · Kasir: {{ $report['cashier']['name'] }}</div>

    <h2>Ringkasan</h2>
    <table>
        <tbody>
            <tr>
                <th>Transaksi</th>
                <td class="right">{{ number_format($report['summary']['transactions'], 0, ',', '.') }}</td>
                <th>Total Transaksi</th>
                <td class="right">{{ $formatMoney($report['summary']['transaction_total']) }}</td>
            </tr>
            <tr>
                <th>Request Approval</th>
                <td class="right">{{ number_format($report['summary']['approval_requests'], 0, ',', '.') }}</td>
                <th>Kas Keluar/Setoran</th>
                <td class="right">{{ number_format($report['summary']['cash_movements'], 0, ',', '.') }}</td>
            </tr>
            <tr>
                <th>Draft</th>
                <td class="right">{{ number_format($report['summary']['drafts'], 0, ',', '.') }}</td>
                <th>Total Kas</th>
                <td class="right">{{ $formatMoney($report['summary']['cash_movement_total']) }}</td>
            </tr>
        </tbody>
    </table>

    <h2>Timeline</h2>
    <table>
        <thead>
            <tr>
                <th>Jam</th>
                <th>Aktivitas</th>
                <th>Status</th>
                <th class="right">Nominal</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report['entries'] as $entry)
                <tr>
                    <td>{{ $entry['time'] }}</td>
                    <td><strong>{{ $entry['title'] }}</strong><br>{{ $entry['description'] }}</td>
                    <td>{{ $entry['status'] ?? '-' }}</td>
                    <td class="right">{{ $entry['amount'] === null ? '-' : $formatMoney($entry['amount']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="4">Belum ada aktivitas.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
