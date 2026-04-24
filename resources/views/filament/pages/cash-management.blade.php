<x-filament-panels::page>
    @php
        $formatMoney = fn (?int $amount): string => (($amount ?? 0) < 0 ? '-Rp' : 'Rp') . number_format(abs($amount ?? 0), 0, ',', '.');
        $shifts = $this->shifts();
        $recentMovements = $this->recentMovements();
    @endphp

    <div class="space-y-6">
        @if (session('status'))
            <div class="rounded-lg border border-success-200 bg-success-50 px-4 py-3 text-sm font-medium text-success-800 dark:border-success-500/30 dark:bg-success-500/10 dark:text-success-300">
                {{ session('status') }}
            </div>
        @endif

        <section class="grid gap-6 xl:grid-cols-[420px_1fr]">
            <form method="POST" action="{{ route('admin.cash-management.store') }}" class="rounded-lg border border-gray-200 bg-white p-5 shadow-sm dark:border-white/10 dark:bg-gray-900">
                @csrf
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Catat Kas</h2>
                <div class="mt-4 space-y-4">
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Shift kasir</span>
                        <select name="cashier_shift_id" required class="block w-full rounded-lg border-gray-300 bg-white text-sm text-gray-950 shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white">
                            @foreach ($shifts as $shift)
                                <option value="{{ $shift->id }}">{{ $shift->user->name }} · {{ $shift->opened_at->format('d M H:i') }} · {{ $shift->status->value }}</option>
                            @endforeach
                        </select>
                    </label>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label>
                            <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Tipe</span>
                            <select name="type" required class="block w-full rounded-lg border-gray-300 bg-white text-sm text-gray-950 shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white">
                                <option value="deposit">Setoran</option>
                                <option value="expense">Kas keluar</option>
                            </select>
                        </label>
                        <label>
                            <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Nominal</span>
                            <input name="amount" type="number" min="1" required class="block w-full rounded-lg border-gray-300 bg-white text-sm text-gray-950 shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white">
                        </label>
                    </div>
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Kategori</span>
                        <input name="category" class="block w-full rounded-lg border-gray-300 bg-white text-sm text-gray-950 shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white" placeholder="Setoran, operasional, bahan">
                    </label>
                    <label class="block">
                        <span class="mb-1 block text-sm font-medium text-gray-700 dark:text-gray-200">Catatan</span>
                        <textarea name="description" rows="3" required class="block w-full rounded-lg border-gray-300 bg-white text-sm text-gray-950 shadow-sm focus:border-primary-500 focus:ring-1 focus:ring-primary-500 dark:border-white/10 dark:bg-white/5 dark:text-white"></textarea>
                    </label>
                    <button class="w-full rounded-lg bg-primary-600 px-4 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-primary-500">
                        Simpan Kas
                    </button>
                </div>
            </form>

            <div class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
                <div class="border-b border-gray-200 px-4 py-3 dark:border-white/10">
                    <h2 class="text-base font-semibold text-gray-950 dark:text-white">Riwayat Kas Terbaru</h2>
                </div>
                <div class="overflow-x-auto">
                    <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                        <thead class="bg-gray-50 dark:bg-white/5">
                            <tr>
                                <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Waktu</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Shift</th>
                                <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Tipe</th>
                                <th class="px-4 py-3 text-right font-medium text-gray-600 dark:text-gray-300">Nominal</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                            @forelse ($recentMovements as $movement)
                                <tr>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $movement->occurred_at->format('d M H:i') }}</td>
                                    <td class="px-4 py-3 font-medium text-gray-950 dark:text-white">{{ $movement->cashierShift->user->name }}</td>
                                    <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $movement->type->value }} · {{ $movement->status->value }}</td>
                                    <td class="px-4 py-3 text-right font-semibold text-gray-950 dark:text-white">{{ $formatMoney($movement->amount) }}</td>
                                </tr>
                            @empty
                                <tr>
                                    <td colspan="4" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Belum ada catatan kas.</td>
                                </tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
            </div>
        </section>
    </div>
</x-filament-panels::page>
