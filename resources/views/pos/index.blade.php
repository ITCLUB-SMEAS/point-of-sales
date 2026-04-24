<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Kasir Fotocopy Sekolah</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-[#f3f1eb] text-zinc-950 antialiased">
    @php
        $canManageBackoffice = auth()->user()->canApproveSensitiveActions();
        $formatMoney = fn (int $amount): string => 'Rp'.number_format($amount, 0, ',', '.');
    @endphp

    <main class="mx-auto flex min-h-screen max-w-[92rem] flex-col gap-5 px-3 py-4 sm:px-5 lg:px-6">
        <header class="rounded-lg border border-zinc-200 bg-white px-4 py-4 shadow-sm sm:px-5">
            <div class="flex flex-col gap-4 lg:flex-row lg:items-center lg:justify-between">
                <div class="min-w-0">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-700">Kasir Toko Fotocopy Sekolah</p>
                    <div class="mt-2 flex flex-wrap items-center gap-3">
                        <h1 class="text-2xl font-semibold tracking-normal sm:text-3xl">POS Cepat</h1>
                        @if ($currentShift)
                            <span class="rounded-md bg-emerald-100 px-2.5 py-1 text-xs font-semibold text-emerald-800">Shift aktif</span>
                        @else
                            <span class="rounded-md bg-zinc-100 px-2.5 py-1 text-xs font-semibold text-zinc-600">Shift belum dibuka</span>
                        @endif
                    </div>
                </div>

                <div class="grid grid-cols-2 gap-2 sm:flex sm:flex-wrap sm:justify-end">
                    <a href="{{ route('pos.index') }}" class="inline-flex items-center justify-center rounded-md border border-zinc-300 bg-white px-4 py-2 text-sm font-semibold text-zinc-900">
                        Kasir
                    </a>
                    @if ($canManageBackoffice)
                        <a href="{{ url('/admin') }}" class="inline-flex items-center justify-center rounded-md bg-zinc-900 px-4 py-2 text-sm font-semibold text-white">
                            Admin
                        </a>
                    @endif
                </div>
            </div>
        </header>

        @if (session('status'))
            <div class="rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        @if ($errors->any())
            <div class="rounded-md border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                <p class="font-semibold">Periksa input transaksi.</p>
                <ul class="mt-2 list-inside list-disc">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        <section class="grid gap-3 sm:grid-cols-2 xl:grid-cols-4">
            <div class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Omzet hari ini</p>
                <p class="mt-2 text-2xl font-semibold">{{ $formatMoney($metrics['gross_revenue']) }}</p>
            </div>
            <div class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">QRIS hari ini</p>
                <p class="mt-2 text-2xl font-semibold">{{ $formatMoney($metrics['qris_revenue']) }}</p>
            </div>
            <div class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Approval pending</p>
                <p class="mt-2 text-2xl font-semibold">{{ $metrics['pending_approvals'] }}</p>
            </div>
            <div class="rounded-lg border border-zinc-200 bg-white p-4 shadow-sm">
                <p class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Stok menipis</p>
                <p class="mt-2 text-2xl font-semibold">{{ $metrics['low_stock_products'] }}</p>
            </div>
        </section>

        @if ($currentShift)
            <section data-pos class="grid items-start gap-5 2xl:grid-cols-[minmax(0,1fr)_430px]">
                <script type="application/json" data-pos-products>
                    {!! json_encode($posProducts, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
                </script>
                <script type="application/json" data-held-carts>
                    {!! json_encode($heldCarts->map(fn ($heldCart): array => [
                        'id' => $heldCart->id,
                        'name' => $heldCart->name,
                        'items' => $heldCart->items,
                        'total' => $heldCart->total,
                    ])->values(), JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
                </script>
                <script type="application/json" data-pos-packages>
                    {!! json_encode($posPackages, JSON_HEX_TAG | JSON_HEX_APOS | JSON_HEX_AMP | JSON_HEX_QUOT) !!}
                </script>

                <div class="space-y-5">
                    <section class="rounded-lg border border-zinc-200 bg-white shadow-sm">
                        <div class="border-b border-zinc-200 p-4 sm:p-5">
                            <div class="flex flex-col gap-4 lg:flex-row lg:items-end lg:justify-between">
                                <div>
                                    <h2 class="text-xl font-semibold">Katalog Cepat</h2>
                                    <p class="mt-1 text-sm text-zinc-500">Klik item untuk masuk keranjang. Cocok untuk fotocopy, print, scan, jilid, laminating, dan ATK.</p>
                                </div>
                                <label class="w-full lg:w-96">
                                    <span class="text-xs font-semibold uppercase tracking-wide text-zinc-500">Cari item</span>
                                    <input
                                        type="search"
                                        data-product-search
                                        class="mt-1 h-11 w-full rounded-md border-zinc-300 text-sm"
                                        placeholder="Cari fotocopy, print, pulpen..."
                                        autocomplete="off"
                                    >
                                </label>
                            </div>
                        </div>

                        @if ($servicePackages->isNotEmpty())
                            <div class="border-b border-zinc-200 bg-amber-50 p-4 sm:p-5">
                                <div class="flex items-center justify-between gap-4">
                                    <div>
                                        <h3 class="text-base font-semibold text-zinc-950">Paket Cepat</h3>
                                        <p class="mt-1 text-sm text-zinc-600">Preset transaksi rutin seperti modul, print tugas, atau jilid cepat.</p>
                                    </div>
                                    <span class="rounded-md bg-white px-2 py-1 text-xs font-semibold text-amber-800">{{ $servicePackages->count() }} paket</span>
                                </div>
                                <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-3">
                                    @foreach ($servicePackages as $servicePackage)
                                        <button
                                            type="button"
                                            data-add-package
                                            data-package-id="{{ $servicePackage->id }}"
                                            class="rounded-md border border-amber-200 bg-white p-3 text-left shadow-sm transition hover:border-amber-400 hover:shadow-md"
                                        >
                                            <span class="block truncate text-sm font-semibold text-zinc-950">{{ $servicePackage->name }}</span>
                                            <span class="mt-1 block text-xs text-zinc-500">{{ $servicePackage->items->count() }} item · {{ $formatMoney($servicePackage->price) }}</span>
                                            @if ($servicePackage->description)
                                                <span class="mt-2 line-clamp-2 block text-xs text-zinc-600">{{ $servicePackage->description }}</span>
                                            @endif
                                        </button>
                                    @endforeach
                                </div>
                            </div>
                        @endif

                        <div class="grid gap-3 p-4 sm:grid-cols-2 sm:p-5 lg:grid-cols-3 xl:grid-cols-4" data-product-grid>
                            @forelse ($products as $product)
                                <button
                                    type="button"
                                    data-add-product
                                    data-product-id="{{ $product->id }}"
                                    data-product-name="{{ str($product->name)->lower() }}"
                                    class="group flex min-h-36 flex-col justify-between rounded-md border border-zinc-200 bg-white p-4 text-left transition hover:-translate-y-0.5 hover:border-amber-400 hover:bg-amber-50/60 hover:shadow-md"
                                >
                                    <span class="min-w-0">
                                        <span class="block truncate text-base font-semibold text-zinc-950">{{ $product->name }}</span>
                                        <span class="mt-1 block text-xs font-medium text-zinc-500">{{ $product->unit }} · {{ $product->type->value === 'service' ? 'Layanan' : 'Barang' }}</span>
                                    </span>
                                    <span class="mt-4 flex items-end justify-between gap-3">
                                        <span>
                                            <span class="block text-lg font-semibold text-zinc-950">{{ $formatMoney($product->price) }}</span>
                                            @if ($product->is_stock_tracked)
                                                <span class="text-xs {{ $product->stock_quantity <= $product->minimum_stock ? 'text-red-600' : 'text-zinc-500' }}">
                                                    Stok {{ $product->stock_quantity }}
                                                </span>
                                            @else
                                                <span class="text-xs text-zinc-500">Tanpa stok</span>
                                            @endif
                                        </span>
                                        <span class="rounded-md bg-zinc-900 px-3 py-2 text-xs font-semibold text-white group-hover:bg-amber-700">
                                            Tambah
                                        </span>
                                    </span>
                                </button>
                            @empty
                                <div class="rounded-md border border-dashed border-zinc-300 bg-zinc-50 p-6 text-sm text-zinc-500 sm:col-span-2 lg:col-span-3">
                                    Belum ada layanan atau barang aktif.
                                </div>
                            @endforelse
                        </div>
                    </section>

                    <section class="grid gap-5 xl:grid-cols-2">
                        <div class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
                            <div class="flex items-center justify-between gap-3">
                                <h2 class="text-lg font-semibold">Draft Ditahan</h2>
                                <span class="rounded-md bg-zinc-100 px-2 py-1 text-xs font-semibold text-zinc-600">{{ $heldCarts->count() }}</span>
                            </div>

                            <div class="mt-4 space-y-3">
                                @forelse ($heldCarts as $heldCart)
                                    <div class="rounded-md border border-zinc-200 p-3">
                                        <div class="flex items-start justify-between gap-3">
                                            <div class="min-w-0">
                                                <p class="truncate text-sm font-semibold text-zinc-950">{{ $heldCart->name }}</p>
                                                <p class="text-xs text-zinc-500">{{ count($heldCart->items) }} item · {{ $formatMoney($heldCart->total) }}</p>
                                            </div>
                                            <p class="text-xs text-zinc-500">{{ $heldCart->created_at->format('H:i') }}</p>
                                        </div>
                                        <div class="mt-3 grid grid-cols-2 gap-2">
                                            <button type="button" data-restore-held-cart="{{ $heldCart->id }}" class="rounded-md bg-zinc-900 px-3 py-2 text-xs font-semibold text-white">
                                                Restore
                                            </button>
                                            <form method="POST" action="{{ route('pos.drafts.destroy', $heldCart) }}">
                                                @csrf
                                                @method('DELETE')
                                                <button class="w-full rounded-md border border-red-200 px-3 py-2 text-xs font-semibold text-red-700">
                                                    Hapus
                                                </button>
                                            </form>
                                        </div>
                                    </div>
                                @empty
                                    <p class="rounded-md border border-dashed border-zinc-300 p-4 text-sm text-zinc-500">Belum ada draft ditahan.</p>
                                @endforelse
                            </div>
                        </div>

                        <div class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
                            <h2 class="text-lg font-semibold">Shift</h2>
                            <dl class="mt-4 space-y-2 text-sm">
                                <div class="flex justify-between gap-4">
                                    <dt class="text-zinc-500">Dibuka</dt>
                                    <dd class="font-medium">{{ $currentShift->opened_at->format('H:i') }}</dd>
                                </div>
                                <div class="flex justify-between gap-4">
                                    <dt class="text-zinc-500">Modal awal</dt>
                                    <dd class="font-medium">{{ $formatMoney($currentShift->opening_cash) }}</dd>
                                </div>
                            </dl>
                            <form method="POST" action="{{ route('pos.shift.close') }}" class="mt-4 space-y-3">
                                @csrf
                                <label class="text-sm font-medium">
                                    Uang fisik saat tutup
                                    <input name="closing_cash" type="number" min="0" required class="mt-1 h-10 w-full rounded-md border-zinc-300 text-sm">
                                </label>
                                <button class="w-full rounded-md bg-zinc-900 px-4 py-2.5 text-sm font-semibold text-white">
                                    Tutup Shift
                                </button>
                            </form>
                        </div>

                        @if ($canManageBackoffice)
                            <div class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm xl:col-span-2">
                                <h2 class="text-lg font-semibold">Kas Keluar / Setoran</h2>
                                <form method="POST" action="{{ route('pos.cash-movements.store') }}" class="mt-4 grid gap-3 md:grid-cols-2">
                                    @csrf
                                    <label class="text-sm font-medium">
                                        Tipe
                                        <select name="type" class="mt-1 h-10 w-full rounded-md border-zinc-300 text-sm">
                                            <option value="expense">Kas keluar</option>
                                            <option value="deposit">Setoran</option>
                                        </select>
                                    </label>
                                    <label class="text-sm font-medium">
                                        Nominal
                                        <input name="amount" type="number" min="1" required class="mt-1 h-10 w-full rounded-md border-zinc-300 text-sm" placeholder="0">
                                    </label>
                                    <label class="text-sm font-medium">
                                        Kategori
                                        <input name="category" class="mt-1 h-10 w-full rounded-md border-zinc-300 text-sm" placeholder="Bahan, setoran, operasional">
                                    </label>
                                    <label class="text-sm font-medium">
                                        Catatan
                                        <input name="description" required class="mt-1 h-10 w-full rounded-md border-zinc-300 text-sm" placeholder="Contoh: beli lakban / setor ke guru piket">
                                    </label>
                                    <button class="rounded-md border border-zinc-300 px-4 py-2.5 text-sm font-semibold text-zinc-800 md:col-span-2">
                                        Catat Kas
                                    </button>
                                </form>

                                <div class="mt-4 grid gap-2 md:grid-cols-2">
                                    @forelse ($cashMovements as $movement)
                                        <div class="flex items-start justify-between gap-3 rounded-md bg-zinc-50 px-3 py-2 text-xs">
                                            <div>
                                                <p class="font-semibold">{{ $movement->type->value === 'expense' ? 'Kas keluar' : 'Setoran' }} · {{ $movement->status->value }}</p>
                                                <p class="text-zinc-500">{{ $movement->description }}</p>
                                            </div>
                                            <p class="font-semibold">{{ $formatMoney($movement->amount) }}</p>
                                        </div>
                                    @empty
                                        <p class="rounded-md border border-dashed border-zinc-300 p-3 text-xs text-zinc-500">Belum ada kas keluar atau setoran.</p>
                                    @endforelse
                                </div>
                            </div>
                        @endif
                    </section>
                </div>

                <aside class="2xl:sticky 2xl:top-4">
                    <form method="POST" action="{{ route('pos.checkout') }}" data-checkout-form class="overflow-hidden rounded-lg border border-zinc-900 bg-white shadow-sm">
                        @csrf

                        <div class="border-b border-zinc-200 bg-zinc-950 p-5 text-white">
                            <div class="flex items-start justify-between gap-4">
                                <div>
                                    <h2 class="text-xl font-semibold">Keranjang</h2>
                                    <p class="mt-1 text-sm text-zinc-300"><span data-cart-count>0</span> item dipilih</p>
                                </div>
                                <button type="button" data-clear-cart class="rounded-md border border-white/20 px-3 py-1.5 text-xs font-semibold text-white">
                                    Kosongkan
                                </button>
                            </div>
                        </div>

                        <div class="max-h-[38rem] overflow-y-auto p-4">
                            <div data-empty-cart class="rounded-md border border-dashed border-zinc-300 p-5 text-center text-sm text-zinc-500">
                                Keranjang masih kosong. Klik tombol Tambah di katalog.
                            </div>
                            <div data-cart-items class="space-y-3"></div>
                        </div>

                        <div class="space-y-4 border-t border-zinc-200 bg-zinc-50 p-5">
                            <div class="flex items-center justify-between gap-4">
                                <span class="text-sm font-medium text-zinc-600">Total</span>
                                <strong class="text-3xl font-semibold tracking-normal" data-cart-total>Rp0</strong>
                            </div>

                            <div class="grid gap-3 sm:grid-cols-2 2xl:grid-cols-1">
                                <label class="text-sm font-medium">
                                    Metode bayar
                                    <select name="payment_method" data-payment-method class="mt-1 h-10 w-full rounded-md border-zinc-300 text-sm">
                                        <option value="cash">Cash</option>
                                        <option value="qris">QRIS</option>
                                    </select>
                                </label>
                                <label class="text-sm font-medium">
                                    Nominal bayar
                                    <input name="payment_amount" data-payment-amount type="number" min="1" required class="mt-1 h-10 w-full rounded-md border-zinc-300 text-sm" placeholder="0">
                                </label>
                            </div>

                            <div class="grid grid-cols-3 gap-2">
                                <button type="button" data-quick-cash="5000" class="rounded-md border border-zinc-300 bg-white px-2 py-2 text-sm font-semibold">5rb</button>
                                <button type="button" data-quick-cash="10000" class="rounded-md border border-zinc-300 bg-white px-2 py-2 text-sm font-semibold">10rb</button>
                                <button type="button" data-quick-cash="20000" class="rounded-md border border-zinc-300 bg-white px-2 py-2 text-sm font-semibold">20rb</button>
                            </div>

                            <div class="flex items-center justify-between rounded-md bg-white px-3 py-2 text-sm ring-1 ring-zinc-200">
                                <span class="font-medium text-zinc-600">Kembalian / kurang</span>
                                <span class="font-semibold" data-change-total>Rp0</span>
                            </div>

                            <label class="block text-sm font-medium">
                                Referensi QRIS
                                <input name="payment_reference" class="mt-1 h-10 w-full rounded-md border-zinc-300 text-sm" placeholder="Opsional">
                            </label>

                            <button data-submit-checkout class="inline-flex w-full items-center justify-center rounded-md bg-amber-600 px-4 py-3 text-sm font-semibold text-white disabled:cursor-not-allowed disabled:bg-zinc-300" disabled>
                                Bayar & Cetak Struk
                            </button>
                            <button type="button" data-hold-cart class="inline-flex w-full items-center justify-center rounded-md border border-amber-300 bg-amber-50 px-4 py-3 text-sm font-semibold text-amber-800 disabled:cursor-not-allowed disabled:border-zinc-200 disabled:bg-zinc-100 disabled:text-zinc-400" disabled>
                                Tahan Keranjang
                            </button>
                        </div>
                    </form>

                    <form id="hold-cart-form" method="POST" action="{{ route('pos.drafts.store') }}" data-hold-cart-form class="hidden">
                        @csrf
                    </form>
                </aside>
            </section>
        @else
            <section class="grid gap-5 lg:grid-cols-[minmax(0,1fr)_420px]">
                <div class="rounded-lg border border-zinc-200 bg-white p-6 shadow-sm">
                    <p class="text-xs font-semibold uppercase tracking-[0.18em] text-amber-700">Mulai operasional</p>
                    <h2 class="mt-2 text-2xl font-semibold">Buka Shift Dulu</h2>
                    <p class="mt-2 max-w-2xl text-sm text-zinc-500">Kasir murid harus membuka shift sebelum menerima transaksi agar kas awal dan selisih kas tercatat.</p>
                </div>

                <form method="POST" action="{{ route('pos.shift.open') }}" class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
                    @csrf
                    <label class="text-sm font-medium">
                        Modal awal
                        <input name="opening_cash" type="number" min="0" required class="mt-1 h-11 w-full rounded-md border-zinc-300 text-sm">
                    </label>
                    <button class="mt-4 w-full rounded-md bg-zinc-900 px-4 py-2.5 text-sm font-semibold text-white">
                        Buka Shift
                    </button>
                </form>
            </section>
        @endif

        <section class="rounded-lg border border-zinc-200 bg-white p-5 shadow-sm">
            <div class="flex items-center justify-between gap-3">
                <h2 class="text-lg font-semibold">Transaksi terakhir</h2>
                <span class="rounded-md bg-zinc-100 px-2 py-1 text-xs font-semibold text-zinc-600">{{ $recentSales->count() }}</span>
            </div>
            <div class="mt-4 grid gap-3 md:grid-cols-2 xl:grid-cols-4">
                @forelse ($recentSales as $sale)
                    <div class="rounded-md border border-zinc-200 p-3">
                        <div class="flex items-start justify-between gap-3">
                            <div class="min-w-0">
                                <a href="{{ route('transactions.receipt', $sale) }}" class="truncate text-sm font-semibold text-amber-700 underline-offset-4 hover:underline">
                                    {{ $sale->number }}
                                </a>
                                <p class="text-xs text-zinc-500">{{ $sale->created_at->format('d M H:i') }}</p>
                            </div>
                            <div class="text-right">
                                <p class="text-sm font-semibold">{{ $formatMoney($sale->total) }}</p>
                                <p class="text-xs text-zinc-500">{{ strtoupper($sale->status->value) }}</p>
                            </div>
                        </div>
                        @if ($sale->status === \App\Enums\TransactionStatus::Completed)
                            <div class="mt-3 space-y-2">
                                <form method="POST" action="{{ route('transactions.void', $sale) }}" class="flex gap-2">
                                    @csrf
                                    <input name="reason" class="min-w-0 flex-1 rounded-md border-zinc-300 text-xs" placeholder="Alasan void">
                                    <button class="rounded-md border border-red-200 px-3 py-1.5 text-xs font-semibold text-red-700">Void</button>
                                </form>
                                <form method="POST" action="{{ route('transactions.refund', $sale) }}" class="flex gap-2">
                                    @csrf
                                    <input name="reason" class="min-w-0 flex-1 rounded-md border-zinc-300 text-xs" placeholder="Alasan refund">
                                    <button class="rounded-md border border-amber-200 px-3 py-1.5 text-xs font-semibold text-amber-700">Refund</button>
                                </form>
                            </div>
                        @endif
                    </div>
                @empty
                    <p class="text-sm text-zinc-500">Belum ada transaksi.</p>
                @endforelse
            </div>
        </section>
    </main>
</body>
</html>
