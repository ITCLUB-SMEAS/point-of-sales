<x-filament-panels::page>
    @php
        $items = $this->items();
    @endphp

    <div class="space-y-6">
        <section class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="text-sm text-gray-500 dark:text-gray-400">Daftar produk aktif yang sudah menyentuh batas minimum stok.</p>
                <p class="mt-1 text-lg font-semibold text-gray-950 dark:text-white">{{ count($items) }} item perlu dicek</p>
            </div>

            <a
                href="{{ route('admin.reorder-list.export.csv') }}"
                class="inline-flex items-center justify-center rounded-lg bg-primary-600 px-3 py-2 text-sm font-semibold text-white shadow-sm hover:bg-primary-500"
            >
                Export CSV
            </a>
        </section>

        <section class="overflow-hidden rounded-lg border border-gray-200 bg-white shadow-sm dark:border-white/10 dark:bg-gray-900">
            <div class="border-b border-gray-200 px-4 py-3 dark:border-white/10">
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Rekomendasi Belanja</h2>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                    <thead class="bg-gray-50 dark:bg-white/5">
                        <tr>
                            <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">Produk</th>
                            <th class="px-4 py-3 text-left font-medium text-gray-600 dark:text-gray-300">SKU</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-600 dark:text-gray-300">Stok</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-600 dark:text-gray-300">Minimum</th>
                            <th class="px-4 py-3 text-right font-medium text-gray-600 dark:text-gray-300">Rekomendasi Beli</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                        @forelse ($items as $item)
                            <tr>
                                <td class="px-4 py-3 font-medium text-gray-950 dark:text-white">{{ $item['name'] }}</td>
                                <td class="px-4 py-3 text-gray-600 dark:text-gray-300">{{ $item['sku'] ?? '-' }}</td>
                                <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">{{ number_format($item['stock_quantity'], 0, ',', '.') }} {{ $item['unit'] }}</td>
                                <td class="px-4 py-3 text-right text-gray-600 dark:text-gray-300">{{ number_format($item['minimum_stock'], 0, ',', '.') }} {{ $item['unit'] }}</td>
                                <td class="px-4 py-3 text-right font-semibold text-danger-600 dark:text-danger-400">{{ number_format($item['recommended_order_quantity'], 0, ',', '.') }} {{ $item['unit'] }}</td>
                            </tr>
                        @empty
                            <tr>
                                <td colspan="5" class="px-4 py-6 text-center text-gray-500 dark:text-gray-400">Tidak ada stok menipis.</td>
                            </tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </section>
    </div>
</x-filament-panels::page>
