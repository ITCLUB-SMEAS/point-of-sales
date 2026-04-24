@php
    $formatMoney = fn (?int $amount): string => ($amount !== null && $amount < 0 ? '-Rp' : 'Rp').number_format(abs($amount ?? 0), 0, ',', '.');
@endphp

<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Laporan Shift {{ $shift->id }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>
        @media print {
            .no-print {
                display: none !important;
            }

            body {
                background: #fff !important;
            }
        }
    </style>
</head>
<body class="bg-zinc-100 text-zinc-950 antialiased">
    <main class="mx-auto min-h-screen max-w-4xl px-4 py-6">
        <nav class="no-print mb-4 flex items-center justify-between">
            <a href="{{ route('pos.index') }}" class="rounded-md border border-zinc-300 px-3 py-2 text-sm font-semibold">Kembali</a>
            <button onclick="window.print()" class="rounded-md bg-zinc-900 px-3 py-2 text-sm font-semibold text-white">Print</button>
        </nav>

        <article class="rounded-md border border-zinc-200 bg-white p-6 shadow-sm print:border-0 print:shadow-none">
            <header class="flex flex-col gap-2 border-b border-zinc-200 pb-5 md:flex-row md:items-end md:justify-between">
                <div>
                    <p class="text-sm font-medium text-amber-700">Kasir Fotocopy Sekolah</p>
                    <h1 class="mt-1 text-2xl font-bold">Laporan Shift</h1>
                </div>
                <p class="text-sm text-zinc-500">#{{ $shift->id }}</p>
            </header>

            <section class="mt-5 grid gap-3 md:grid-cols-4">
                <div class="rounded-md bg-zinc-50 p-4">
                    <p class="text-sm text-zinc-500">Omzet</p>
                    <p class="mt-1 text-xl font-semibold">{{ $formatMoney($summary['gross_revenue']) }}</p>
                </div>
                <div class="rounded-md bg-zinc-50 p-4">
                    <p class="text-sm text-zinc-500">Cash</p>
                    <p class="mt-1 text-xl font-semibold">{{ $formatMoney($summary['cash_revenue']) }}</p>
                </div>
                <div class="rounded-md bg-zinc-50 p-4">
                    <p class="text-sm text-zinc-500">QRIS</p>
                    <p class="mt-1 text-xl font-semibold">{{ $formatMoney($summary['qris_revenue']) }}</p>
                </div>
                <div class="rounded-md bg-zinc-50 p-4">
                    <p class="text-sm text-zinc-500">Selisih kas</p>
                    <p class="mt-1 text-xl font-semibold">{{ $formatMoney($shift->cash_variance) }}</p>
                </div>
                <div class="rounded-md bg-zinc-50 p-4">
                    <p class="text-sm text-zinc-500">Kas keluar</p>
                    <p class="mt-1 text-xl font-semibold">{{ $formatMoney($summary['cash_expense_total']) }}</p>
                </div>
                <div class="rounded-md bg-zinc-50 p-4">
                    <p class="text-sm text-zinc-500">Setoran</p>
                    <p class="mt-1 text-xl font-semibold">{{ $formatMoney($summary['cash_deposit_total']) }}</p>
                </div>
            </section>

            <section class="mt-6 grid gap-4 md:grid-cols-2">
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">Kasir</dt>
                        <dd class="font-medium">{{ $shift->user->name }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">Dibuka</dt>
                        <dd class="font-medium">{{ $shift->opened_at->format('d M Y H:i') }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">Ditutup</dt>
                        <dd class="font-medium">{{ $shift->closed_at?->format('d M Y H:i') ?? '-' }}</dd>
                    </div>
                </dl>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">Modal awal</dt>
                        <dd class="font-medium">{{ $formatMoney($shift->opening_cash) }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">Kas seharusnya</dt>
                        <dd class="font-medium">{{ $formatMoney($shift->expected_closing_cash) }}</dd>
                    </div>
                    <div class="flex justify-between gap-4">
                        <dt class="text-zinc-500">Kas fisik</dt>
                        <dd class="font-medium">{{ $formatMoney($shift->closing_cash) }}</dd>
                    </div>
                </dl>
            </section>

            <section class="mt-6">
                <h2 class="text-lg font-semibold">Kas Keluar & Setoran</h2>
                <div class="mt-3 overflow-hidden rounded-md border border-zinc-200">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-zinc-50 text-zinc-500">
                            <tr>
                                <th class="px-3 py-2">Waktu</th>
                                <th class="px-3 py-2">Tipe</th>
                                <th class="px-3 py-2">Status</th>
                                <th class="px-3 py-2 text-right">Nominal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200">
                            @forelse ($shift->cashMovements as $movement)
                                <tr>
                                    <td class="px-3 py-2">{{ $movement->occurred_at->format('H:i') }}</td>
                                    <td class="px-3 py-2">{{ $movement->type->value }}</td>
                                    <td class="px-3 py-2">{{ $movement->status->value }}</td>
                                    <td class="px-3 py-2 text-right font-medium">{{ $formatMoney($movement->amount) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-3 py-4 text-center text-zinc-500">Belum ada kas keluar atau setoran.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>

            <section class="mt-6">
                <h2 class="text-lg font-semibold">Transaksi Shift</h2>
                <div class="mt-3 overflow-hidden rounded-md border border-zinc-200">
                    <table class="w-full text-left text-sm">
                        <thead class="bg-zinc-50 text-zinc-500">
                            <tr>
                                <th class="px-3 py-2">Nomor</th>
                                <th class="px-3 py-2">Waktu</th>
                                <th class="px-3 py-2">Status</th>
                                <th class="px-3 py-2 text-right">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-zinc-200">
                            @foreach ($shift->saleTransactions as $sale)
                                <tr>
                                    <td class="px-3 py-2 font-medium">{{ $sale->number }}</td>
                                    <td class="px-3 py-2">{{ $sale->created_at->format('H:i') }}</td>
                                    <td class="px-3 py-2">{{ $sale->status->value }}</td>
                                    <td class="px-3 py-2 text-right font-medium">{{ $formatMoney($sale->total) }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </section>
        </article>
    </main>
</body>
</html>
