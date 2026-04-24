<x-filament-widgets::widget>
    @php
        $items = $this->items();
    @endphp

    <x-filament::section>
        <div class="flex flex-col gap-3 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <h2 class="text-base font-semibold text-gray-950 dark:text-white">Stok Menipis</h2>
                <p class="text-sm text-gray-500 dark:text-gray-400">{{ count($items) }} item prioritas perlu dicek.</p>
            </div>

            <a
                href="{{ $this->reorderListUrl() }}"
                class="inline-flex items-center justify-center rounded-lg border border-gray-300 bg-white px-3 py-2 text-sm font-semibold text-gray-800 shadow-sm hover:bg-gray-50 dark:border-white/10 dark:bg-white/5 dark:text-gray-100"
            >
                Lihat Reorder List
            </a>
        </div>

        <div class="mt-4 overflow-x-auto">
            <table class="w-full divide-y divide-gray-200 text-sm dark:divide-white/10">
                <thead class="bg-gray-50 dark:bg-white/5">
                    <tr>
                        <th class="px-3 py-2 text-left font-medium text-gray-600 dark:text-gray-300">Produk</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Stok</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Minimum</th>
                        <th class="px-3 py-2 text-right font-medium text-gray-600 dark:text-gray-300">Beli</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-gray-100 dark:divide-white/10">
                    @forelse ($items as $item)
                        <tr>
                            <td class="px-3 py-2 font-medium text-gray-950 dark:text-white">
                                {{ $item['name'] }}
                                <span class="block text-xs font-normal text-gray-500 dark:text-gray-400">{{ $item['sku'] ?? 'Tanpa SKU' }}</span>
                            </td>
                            <td class="px-3 py-2 text-right text-gray-600 dark:text-gray-300">{{ number_format($item['stock_quantity'], 0, ',', '.') }} {{ $item['unit'] }}</td>
                            <td class="px-3 py-2 text-right text-gray-600 dark:text-gray-300">{{ number_format($item['minimum_stock'], 0, ',', '.') }} {{ $item['unit'] }}</td>
                            <td class="px-3 py-2 text-right font-semibold text-danger-600 dark:text-danger-400">{{ number_format($item['recommended_order_quantity'], 0, ',', '.') }} {{ $item['unit'] }}</td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="4" class="px-3 py-5 text-center text-gray-500 dark:text-gray-400">Tidak ada stok menipis.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </x-filament::section>
</x-filament-widgets::widget>
