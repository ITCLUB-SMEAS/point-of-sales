<?php

namespace App\Http\Controllers;

use App\Services\ReorderList;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReorderListExportController extends Controller
{
    public function csv(Request $request, ReorderList $reorderList): StreamedResponse
    {
        if (! $request->user()->canApproveSensitiveActions()) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $date = today()->toDateString();

        return response()->streamDownload(function () use ($date, $reorderList): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Daftar Belanja Stok']);
            fputcsv($handle, ['Tanggal', $date]);
            fputcsv($handle, []);
            fputcsv($handle, ['Produk', 'SKU', 'Stok Saat Ini', 'Stok Minimum', 'Rekomendasi Beli', 'Satuan']);

            foreach ($reorderList->items() as $item) {
                fputcsv($handle, [
                    $item['name'],
                    $item['sku'],
                    $item['stock_quantity'],
                    $item['minimum_stock'],
                    $item['recommended_order_quantity'],
                    $item['unit'],
                ]);
            }

            fclose($handle);
        }, "daftar-belanja-stok-{$date}.csv", [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }
}
