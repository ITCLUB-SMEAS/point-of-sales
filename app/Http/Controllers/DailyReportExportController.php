<?php

namespace App\Http\Controllers;

use App\Services\DailyReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DailyReportExportController extends Controller
{
    public function csv(Request $request, DailyReport $dailyReport): StreamedResponse
    {
        $report = $dailyReport->forDate($this->validatedDate($request));
        $filename = "laporan-harian-{$report['date']}.csv";

        return response()->streamDownload(function () use ($report): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Laporan Harian', $report['date']]);
            fputcsv($handle, []);
            fputcsv($handle, ['Ringkasan']);
            fputcsv($handle, ['Omzet Total', $report['summary']['gross_revenue']]);
            fputcsv($handle, ['Omzet Bersih', $report['summary']['net_revenue']]);
            fputcsv($handle, ['Harga Modal', $report['summary']['cost_total']]);
            fputcsv($handle, ['Laba Kotor', $report['summary']['gross_profit']]);
            fputcsv($handle, ['Cash', $report['summary']['cash_revenue']]);
            fputcsv($handle, ['QRIS', $report['summary']['qris_revenue']]);
            fputcsv($handle, ['Retur Item', $report['summary']['refund_total']]);
            fputcsv($handle, ['Kas Keluar', $report['summary']['cash_expense_total']]);
            fputcsv($handle, ['Setoran', $report['summary']['cash_deposit_total']]);
            fputcsv($handle, ['Transaksi Selesai', $report['summary']['transaction_count']]);
            fputcsv($handle, ['Void', $report['summary']['void_count']]);
            fputcsv($handle, ['Approval Pending', $report['summary']['pending_approvals']]);
            fputcsv($handle, ['Selisih Kas', $report['summary']['cash_variance_total']]);

            fputcsv($handle, []);
            fputcsv($handle, ['Shift Kasir']);
            fputcsv($handle, ['Kasir', 'Dibuka', 'Ditutup', 'Kas Sistem', 'Kas Fisik', 'Selisih']);

            foreach ($report['shifts'] as $shift) {
                fputcsv($handle, [
                    $shift['cashier_name'],
                    $shift['opened_at'],
                    $shift['closed_at'] ?? 'Berjalan',
                    $shift['expected_closing_cash'],
                    $shift['closing_cash'],
                    $shift['cash_variance'],
                ]);
            }

            fputcsv($handle, []);
            fputcsv($handle, ['Produk Terlaris']);
            fputcsv($handle, ['Produk', 'Qty', 'Omzet']);

            foreach ($report['top_products'] as $product) {
                fputcsv($handle, [
                    $product['product_name'],
                    $product['quantity_sold'],
                    $product['revenue'],
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function pdf(Request $request, DailyReport $dailyReport): Response
    {
        $report = $dailyReport->forDate($this->validatedDate($request));

        return Pdf::loadView('reports.daily-report-pdf', [
            'report' => $report,
        ])
            ->setPaper('a4')
            ->download("laporan-harian-{$report['date']}.pdf");
    }

    private function validatedDate(Request $request): string
    {
        if (! $request->user()->canApproveSensitiveActions()) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);

        return CarbonImmutable::parse($validated['date'] ?? today())->toDateString();
    }
}
