<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Struk {{ $sale->number }}</title>
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
    <main class="mx-auto min-h-screen max-w-md px-4 py-6">
        <nav class="no-print mb-4 flex items-center justify-between">
            <a href="{{ route('pos.index') }}" class="rounded-md border border-zinc-300 px-3 py-2 text-sm font-semibold">Kembali</a>
            <button onclick="window.print()" class="rounded-md bg-zinc-900 px-3 py-2 text-sm font-semibold text-white">Print</button>
        </nav>

        @if (session('status'))
            <div class="no-print mb-4 rounded-md border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm font-medium text-emerald-800">
                {{ session('status') }}
            </div>
        @endif

        <article class="rounded-md border border-zinc-200 bg-white p-5 shadow-sm print:border-0 print:shadow-none">
            <header class="border-b border-dashed border-zinc-300 pb-4 text-center">
                <p class="text-sm font-semibold uppercase tracking-normal">Toko Fotocopy Sekolah</p>
                <h1 class="mt-2 text-xl font-bold">Struk Transaksi</h1>
                <p class="mt-1 text-sm text-zinc-500">{{ $sale->number }}</p>
            </header>

            <dl class="mt-4 grid grid-cols-2 gap-2 text-sm">
                <dt class="text-zinc-500">Kasir</dt>
                <dd class="text-right font-medium">{{ $sale->cashier->name }}</dd>
                <dt class="text-zinc-500">Waktu</dt>
                <dd class="text-right font-medium">{{ $sale->created_at->format('d M Y H:i') }}</dd>
            </dl>

            <div class="mt-5 space-y-3 border-y border-dashed border-zinc-300 py-4">
                @foreach ($sale->items as $item)
                    <div class="text-sm">
                        <div class="flex justify-between gap-4">
                            <p class="font-semibold">{{ $item->product_name }}</p>
                            <p class="font-semibold">Rp{{ number_format($item->subtotal, 0, ',', '.') }}</p>
                        </div>
                        <p class="mt-1 text-xs text-zinc-500">
                            {{ $item->quantity }} x Rp{{ number_format($item->unit_price, 0, ',', '.') }}
                            @if ($item->source_note)
                                <span> | {{ $item->source_note }}</span>
                            @endif
                        </p>
                        @if (in_array($sale->status, [\App\Enums\TransactionStatus::Completed, \App\Enums\TransactionStatus::PartiallyRefunded], true))
                            <form method="POST" action="{{ route('transactions.partial-refund', $sale) }}" class="no-print mt-2 grid gap-2">
                                @csrf
                                <input type="hidden" name="items[0][sale_transaction_item_id]" value="{{ $item->id }}">
                                <input name="reason" required class="rounded-md border-zinc-300 text-xs" placeholder="Alasan retur">
                                <div class="grid grid-cols-[1fr_auto] gap-2">
                                    <label class="sr-only">Jumlah retur</label>
                                    <input name="items[0][quantity]" type="number" min="1" max="{{ $item->quantity }}" required class="rounded-md border-zinc-300 text-xs" placeholder="Qty retur">
                                    <button class="rounded-md border border-amber-200 px-3 py-1.5 text-xs font-semibold text-amber-700">Retur Item</button>
                                </div>
                            </form>
                        @endif
                    </div>
                @endforeach
            </div>

            <dl class="mt-4 space-y-2 text-sm">
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500">Subtotal</dt>
                    <dd class="font-medium">Rp{{ number_format($sale->subtotal, 0, ',', '.') }}</dd>
                </div>
                <div class="flex justify-between gap-4">
                    <dt class="text-zinc-500">Diskon</dt>
                    <dd class="font-medium">Rp{{ number_format($sale->discount_total, 0, ',', '.') }}</dd>
                </div>
                <div class="flex justify-between gap-4 border-t border-zinc-200 pt-2 text-base">
                    <dt class="font-semibold">Total</dt>
                    <dd class="font-bold">Rp{{ number_format($sale->total, 0, ',', '.') }}</dd>
                </div>
            </dl>

            <div class="mt-5 space-y-2 rounded-md bg-zinc-50 p-3 text-sm">
                @foreach ($sale->payments as $payment)
                    <div class="flex justify-between gap-4">
                        <p>
                            {{ strtoupper($payment->method->value) }}
                            @if ($payment->reference)
                                <span class="text-zinc-500">({{ $payment->reference }})</span>
                            @endif
                        </p>
                        <p class="font-semibold">Rp{{ number_format($payment->amount, 0, ',', '.') }}</p>
                    </div>
                @endforeach
                <div class="flex justify-between gap-4 border-t border-zinc-200 pt-2">
                    <p>Kembalian</p>
                    <p class="font-semibold">Rp{{ number_format($sale->change_total, 0, ',', '.') }}</p>
                </div>
            </div>

            <p class="mt-5 text-center text-xs text-zinc-500">Terima kasih.</p>
        </article>
    </main>
</body>
</html>
