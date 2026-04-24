<!DOCTYPE html>
<html lang="id">
<head>
    <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
    <title>Laporan Harian {{ $report['date'] }}</title>
    <style>
        body {
            color: #18181b;
            font-family: DejaVu Sans, sans-serif;
            font-size: 12px;
            line-height: 1.45;
        }

        h1 {
            font-size: 22px;
            margin: 0 0 4px;
        }

        h2 {
            border-bottom: 1px solid #d4d4d8;
            font-size: 15px;
            margin: 22px 0 8px;
            padding-bottom: 5px;
        }

        table {
            border-collapse: collapse;
            width: 100%;
        }

        th,
        td {
            border: 1px solid #d4d4d8;
            padding: 7px;
            vertical-align: top;
        }

        th {
            background: #f4f4f5;
            font-weight: 700;
            text-align: left;
        }

        .muted {
            color: #71717a;
        }

        .right {
            text-align: right;
        }

        .summary {
            margin-top: 16px;
        }
    </style>
</head>
<body>
    @php
        $formatMoney = fn (?int $amount): string => (($amount ?? 0) < 0 ? '-Rp' : 'Rp') . number_format(abs($amount ?? 0), 0, ',', '.');
    @endphp

    <h1>Laporan Harian</h1>
    <div class="muted">Tanggal: {{ $report['date'] }}</div>

    <table class="summary">
        <tbody>
            <tr>
                <th>Omzet Total</th>
                <td class="right">{{ $formatMoney($report['summary']['gross_revenue']) }}</td>
                <th>Omzet Bersih</th>
                <td class="right">{{ $formatMoney($report['summary']['net_revenue']) }}</td>
            </tr>
            <tr>
                <th>Cash</th>
                <td class="right">{{ $formatMoney($report['summary']['cash_revenue']) }}</td>
                <th>QRIS</th>
                <td class="right">{{ $formatMoney($report['summary']['qris_revenue']) }}</td>
            </tr>
            <tr>
                <th>Harga Modal</th>
                <td class="right">{{ $formatMoney($report['summary']['cost_total']) }}</td>
                <th>Laba Kotor</th>
                <td class="right">{{ $formatMoney($report['summary']['gross_profit']) }}</td>
            </tr>
            <tr>
                <th>Retur Item</th>
                <td class="right">{{ $formatMoney($report['summary']['refund_total']) }}</td>
                <th>Kas Keluar</th>
                <td class="right">{{ $formatMoney($report['summary']['cash_expense_total']) }}</td>
            </tr>
            <tr>
                <th>Setoran</th>
                <td class="right">{{ $formatMoney($report['summary']['cash_deposit_total']) }}</td>
                <th>Selisih Kas</th>
                <td class="right">{{ $formatMoney($report['summary']['cash_variance_total']) }}</td>
            </tr>
            <tr>
                <th>Transaksi Selesai</th>
                <td class="right">{{ number_format($report['summary']['transaction_count'], 0, ',', '.') }}</td>
                <th>Void</th>
                <td class="right">{{ number_format($report['summary']['void_count'], 0, ',', '.') }}</td>
            </tr>
            <tr>
                <th>Approval Pending</th>
                <td class="right">{{ number_format($report['summary']['pending_approvals'], 0, ',', '.') }}</td>
                <th></th>
                <td></td>
            </tr>
        </tbody>
    </table>

    <h2>Shift Kasir</h2>
    <table>
        <thead>
            <tr>
                <th>Kasir</th>
                <th>Jam</th>
                <th class="right">Kas Sistem</th>
                <th class="right">Kas Fisik</th>
                <th class="right">Selisih</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report['shifts'] as $shift)
                <tr>
                    <td>{{ $shift['cashier_name'] }}</td>
                    <td>{{ $shift['opened_at'] }} - {{ $shift['closed_at'] ?? 'Berjalan' }}</td>
                    <td class="right">{{ $formatMoney($shift['expected_closing_cash']) }}</td>
                    <td class="right">{{ $formatMoney($shift['closing_cash']) }}</td>
                    <td class="right">{{ $formatMoney($shift['cash_variance']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="5">Belum ada shift pada tanggal ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>

    <h2>Produk Terlaris</h2>
    <table>
        <thead>
            <tr>
                <th>Produk</th>
                <th class="right">Qty</th>
                <th class="right">Omzet</th>
            </tr>
        </thead>
        <tbody>
            @forelse ($report['top_products'] as $product)
                <tr>
                    <td>{{ $product['product_name'] }}</td>
                    <td class="right">{{ number_format($product['quantity_sold'], 0, ',', '.') }}</td>
                    <td class="right">{{ $formatMoney($product['revenue']) }}</td>
                </tr>
            @empty
                <tr>
                    <td colspan="3">Belum ada penjualan pada tanggal ini.</td>
                </tr>
            @endforelse
        </tbody>
    </table>
</body>
</html>
