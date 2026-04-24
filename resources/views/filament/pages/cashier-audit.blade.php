<x-filament-panels::page>
    @php
        $report = $this->report();
        $cashiers = $this->cashiers();
        $formatMoney = fn (?int $amount): string => (($amount ?? 0) < 0 ? '-Rp' : 'Rp') . number_format(abs($amount ?? 0), 0, ',', '.');
    @endphp

    <div class="space-y-6">
        <section class="flex flex-col gap-3 sm:flex-row sm:items-end sm:justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Riwayat tindakan kasir murid</p>
                <p class="text-lg font-semibold text-gray-950 dark:text-white">Audit Detail per Kasir</p>
            </div>

            <div class="grid w-full gap-3 sm:w-auto sm:grid-cols-2">
                <label>
                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Kasir</span>
                    <select
                        wire:model.live="cashier"
                        class="block w-full rounded-lg border-gray-300 bg-white text-sm text-gray-950 shadow-sm transition duration-75 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
                    >
                        @foreach ($cashiers as $cashierOption)
                            <option value="{{ $cashierOption->id }}">{{ $cashierOption->name }}</option>
                        @endforeach
                    </select>
                </label>

                <label>
                    <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Tanggal</span>
                    <input
                        type="date"
                        wire:model.live="date"
                        class="block w-full rounded-lg border-gray-300 bg-white text-sm text-gray-950 shadow-sm transition duration-75 focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white"
                    >
                </label>
            </div>
        </section>

        @if ($report)
            <div class="flex flex-wrap gap-2">
                <a
                    href="{{ route('admin.cashier-audit.export.csv', ['cashier' => $report['cashier']['id'], 'date' => $report['date']]) }}"
                    class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-800 shadow-sm hover:bg-gray-50 dark:border-white/10 dark:bg-white/5 dark:text-gray-100"
                >
                    Export CSV
                </a>
                <a
                    href="{{ route('admin.cashier-audit.export.pdf', ['cashier' => $report['cashier']['id'], 'date' => $report['date']]) }}"
                    class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500"
                >
                    Export PDF
                </a>
            </div>

            <section class="grid gap-4 md:grid-cols-2 xl:grid-cols-5">
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Kasir</p>
                    <p class="mt-2 text-xl font-semibold text-gray-950 dark:text-white">{{ $report['cashier']['name'] }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Transaksi</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($report['summary']['transactions'], 0, ',', '.') }}</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $formatMoney($report['summary']['transaction_total']) }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Request Approval</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($report['summary']['approval_requests'], 0, ',', '.') }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Kas Keluar/Setoran</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($report['summary']['cash_movements'], 0, ',', '.') }}</p>
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">{{ $formatMoney($report['summary']['cash_movement_total']) }}</p>
                </div>
                <div class="rounded-lg border border-gray-200 bg-white p-4 shadow-sm dark:border-white/10 dark:bg-gray-900">
                    <p class="text-sm text-gray-500 dark:text-gray-400">Draft</p>
                    <p class="mt-2 text-2xl font-semibold text-gray-950 dark:text-white">{{ number_format($report['summary']['drafts'], 0, ',', '.') }}</p>
                </div>
            </section>

            <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-4 py-3 dark:border-white/10">
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">Timeline Aktivitas</h2>
                </div>

                <div class="divide-y divide-gray-100 dark:divide-white/10">
                    @forelse ($report['entries'] as $entry)
                        <div class="grid gap-3 px-4 py-4 md:grid-cols-[5rem_1fr_auto] md:items-start">
                            <div class="text-sm font-semibold text-gray-950 dark:text-white">{{ $entry['time'] }}</div>
                            <div>
                                <div class="flex flex-wrap items-center gap-2">
                                    <p class="font-semibold text-gray-950 dark:text-white">{{ $entry['title'] }}</p>
                                    @if ($entry['status'])
                                        <span class="rounded-md bg-gray-100 px-2 py-1 text-xs font-semibold text-gray-700 dark:bg-white/10 dark:text-gray-200">{{ $entry['status'] }}</span>
                                    @endif
                                </div>
                                <p class="mt-1 text-sm text-gray-600 dark:text-gray-300">{{ $entry['description'] }}</p>
                                @if ($entry['reference'])
                                    <a href="{{ $entry['reference'] }}" class="mt-2 inline-flex text-sm font-semibold text-primary-600 hover:text-primary-500">
                                        Lihat detail
                                    </a>
                                @endif
                            </div>
                            <div class="text-left text-sm font-semibold text-gray-950 dark:text-white md:text-right">
                                {{ $entry['amount'] === null ? '-' : $formatMoney($entry['amount']) }}
                            </div>
                        </div>
                    @empty
                        <p class="px-4 py-8 text-center text-sm text-gray-500 dark:text-gray-400">Belum ada aktivitas untuk kasir dan tanggal ini.</p>
                    @endforelse
                </div>
            </section>
        @else
            <section class="rounded-lg border border-dashed border-gray-300 bg-white p-8 text-center text-sm text-gray-500 shadow-sm dark:border-white/10 dark:bg-gray-900 dark:text-gray-400">
                Belum ada kasir murid yang bisa diaudit.
            </section>
        @endif
    </div>
</x-filament-panels::page>
