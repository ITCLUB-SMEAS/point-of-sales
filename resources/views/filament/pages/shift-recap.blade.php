<x-filament-panels::page>
    @php
        $formatMoney = fn (?int $amount): string => (($amount ?? 0) < 0 ? '-Rp' : 'Rp') . number_format(abs($amount ?? 0), 0, ',', '.');
        $report = $this->report();
        $shifts = $this->shifts();
    @endphp

    <div class="space-y-6">
        <section class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Transaksi, kas, draft, dan selisih per shift</p>
                <p class="text-lg font-semibold text-gray-950 dark:text-white">Rekap Shift</p>
            </div>
            <form method="GET" action="{{ url('/admin/shift-recap') }}" class="flex w-full gap-2 sm:w-auto">
                <select name="shift" class="block w-full rounded-lg border-gray-300 bg-white text-sm text-gray-950 shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white sm:w-80">
                    @foreach ($shifts as $shiftOption)
                        <option value="{{ $shiftOption->id }}" @selected($report && $report['id'] === $shiftOption->id)>
                            {{ $shiftOption->user->name }} · {{ $shiftOption->opened_at->format('d M H:i') }}
                        </option>
                    @endforeach
                </select>
                <button class="rounded-lg bg-primary-600 px-4 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500">Lihat</button>
            </form>
        </section>

        @if ($report)
            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                @foreach ([
                    'Omzet' => $formatMoney($report['summary']['gross_revenue']),
                    'Cash' => $formatMoney($report['summary']['cash_revenue']),
                    'QRIS' => $formatMoney($report['summary']['qris_revenue']),
                    'Setoran' => $formatMoney($report['summary']['cash_deposit_total']),
                    'Selisih' => $formatMoney($report['summary']['cash_variance']),
                ] as $label => $value)
                    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ $value }}</p>
                    </div>
                @endforeach
            </section>

            <section class="grid gap-4 md:grid-cols-2">
                @foreach ([
                    'Transaksi' => $report['summary']['transaction_count'],
                    'Draft' => $report['summary']['draft_count'],
                ] as $label => $value)
                    <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                        <p class="text-sm text-gray-500 dark:text-gray-400">{{ $label }}</p>
                        <p class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($value, 0, ',', '.') }}</p>
                    </div>
                @endforeach
            </section>

            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-4 py-3 dark:border-white/10">
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">{{ $report['cashier_name'] }} · {{ $report['opened_at'] }}</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Jam</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Transaksi</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Status</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-600 dark:text-gray-300">Total</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                            @forelse ($report['transactions'] as $transaction)
                                <tr>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $transaction['created_at'] }}</td>
                                    <td class="px-4 py-3 font-medium text-gray-950 dark:text-white">{{ $transaction['number'] }}</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $transaction['status'] }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-950 dark:text-white">{{ $formatMoney($transaction['total']) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Belum ada transaksi pada shift ini.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </section>
        @else
            <section class="rounded-lg border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500 shadow-sm dark:border-white/10 dark:bg-gray-900 dark:text-gray-400">
                Belum ada shift yang bisa direkap.
            </section>
        @endif
    </div>
</x-filament-panels::page>
