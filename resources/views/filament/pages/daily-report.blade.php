<x-filament-panels::page>
    @php
        $report = $this->report();
        $summary = $report['summary'];
        $formatMoney = fn (?int $amount): string => (($amount ?? 0) < 0 ? '-Rp' : 'Rp') . number_format(abs($amount ?? 0), 0, ',', '.');
    @endphp

    <div class="space-y-6">
        <section class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Tanggal laporan</p>
                <p class="text-lg font-semibold text-gray-950 dark:text-white">{{ $report['date'] }}</p>
            </div>

            <div class="flex w-full flex-col gap-3 sm:w-auto sm:flex-row sm:items-end">
                <label class="w-full sm:w-56">
                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Pilih tanggal</span>
                    <input
                        type="date"
                        wire:model.live="date"
                        class="block w-full rounded-lg border-gray-300 bg-white text-sm text-gray-950 shadow-sm transition duration-75 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
                    >
                </label>

                <div class="grid grid-cols-2 gap-2">
                    <a
                        href="{{ route('admin.daily-report.export.csv', ['date' => $report['date']]) }}"
                        class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-800 shadow-sm hover:bg-gray-50 dark:border-white/10 dark:bg-white/5 dark:text-gray-100"
                    >
                        Export CSV
                    </a>
                    <a
                        href="{{ route('admin.daily-report.export.pdf', ['date' => $report['date']]) }}"
                        class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500"
                    >
                        Export PDF
                    </a>
                </div>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">Omzet Total</p>
                <p class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ $formatMoney($summary['gross_revenue']) }}</p>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">Omzet Bersih</p>
                <p class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ $formatMoney($summary['net_revenue']) }}</p>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">Cash</p>
                <p class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ $formatMoney($summary['cash_revenue']) }}</p>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">QRIS</p>
                <p class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ $formatMoney($summary['qris_revenue']) }}</p>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">Harga Modal</p>
                <p class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ $formatMoney($summary['cost_total']) }}</p>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">Laba Kotor</p>
                <p class="mt-2 text-2xl font-semibold {{ $summary['gross_profit'] < 0 ? 'text-danger-600 dark:text-danger-400' : 'text-success-700 dark:text-success-400' }}">{{ $formatMoney($summary['gross_profit']) }}</p>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">Selisih Kas</p>
                <p class="mt-2 text-2xl font-semibold {{ $summary['cash_variance_total'] < 0 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-950 dark:text-white' }}">
                    {{ $formatMoney($summary['cash_variance_total']) }}
                </p>
            </div>
        </section>

        <section class="grid gap-4 md:grid-cols-3">
            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">Retur Item</p>
                <p class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ $formatMoney($summary['refund_total']) }}</p>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">Kas Keluar</p>
                <p class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ $formatMoney($summary['cash_expense_total']) }}</p>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">Setoran</p>
                <p class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ $formatMoney($summary['cash_deposit_total']) }}</p>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">Transaksi Selesai</p>
                <p class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($summary['transaction_count'], 0, ',', '.') }}</p>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">Void</p>
                <p class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($summary['void_count'], 0, ',', '.') }}</p>
            </div>

            <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                <p class="text-sm text-gray-500 dark:text-gray-400">Approval Pending</p>
                <p class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($summary['pending_approvals'], 0, ',', '.') }}</p>
            </div>
        </section>

        <section class="grid gap-6 xl:grid-cols-2">
            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-4 py-3 dark:border-white/10">
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">Shift Kasir</h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Kasir</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Jam</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-600 dark:text-gray-300">Kas Sistem</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-600 dark:text-gray-300">Selisih</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                            @forelse ($report['shifts'] as $shift)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-gray-950 dark:text-white">{{ $shift['cashier_name'] }}</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $shift['opened_at'] }} - {{ $shift['closed_at'] ?? 'Berjalan' }}</td>
                                    <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">{{ $formatMoney($shift['expected_closing_cash']) }}</td>
                                    <td class="px-4 py-3 text-right font-medium {{ ($shift['cash_variance'] ?? 0) < 0 ? 'text-danger-600 dark:text-danger-400' : 'text-gray-950 dark:text-white' }}">
                                        {{ $formatMoney($shift['cash_variance']) }}
                                    </td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Belum ada shift pada tanggal ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>

            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-4 py-3 dark:border-white/10">
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">Produk Terlaris</h2>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Produk</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-600 dark:text-gray-300">Qty</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-600 dark:text-gray-300">Omzet</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                            @forelse ($report['top_products'] as $product)
                                <tr>
                                    <td class="px-4 py-3 font-medium text-gray-950 dark:text-white">{{ $product['product_name'] }}</td>
                                    <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">{{ number_format($product['quantity_sold'], 0, ',', '.') }}</td>
                                    <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">{{ $formatMoney($product['revenue']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="3" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Belum ada penjualan pada tanggal ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</x-filament-panels::page>
