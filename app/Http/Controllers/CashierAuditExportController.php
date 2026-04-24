<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\CashierAuditReport;
use Barryvdh\DomPDF\Facade\Pdf;
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CashierAuditExportController extends Controller
{
    public function csv(Request $request, CashierAuditReport $cashierAuditReport): StreamedResponse
    {
        $report = $this->report($request, $cashierAuditReport);
        $filename = "audit-kasir-{$report['cashier']['id']}-{$report['date']}.csv";

        return response()->streamDownload(function () use ($report): void {
            $handle = fopen('php://output', 'w');

            fputcsv($handle, ['Audit Kasir', $report['date']]);
            fputcsv($handle, ['Kasir', $report['cashier']['name']]);
            fputcsv($handle, []);
            fputcsv($handle, ['Ringkasan']);
            fputcsv($handle, ['Transaksi', $report['summary']['transactions']]);
            fputcsv($handle, ['Request Approval', $report['summary']['approval_requests']]);
            fputcsv($handle, ['Kas Keluar/Setoran', $report['summary']['cash_movements']]);
            fputcsv($handle, ['Draft', $report['summary']['drafts']]);
            fputcsv($handle, ['Total Transaksi', $report['summary']['transaction_total']]);
            fputcsv($handle, []);
            fputcsv($handle, ['Jam', 'Tipe', 'Judul', 'Deskripsi', 'Status', 'Nominal']);

            foreach ($report['entries'] as $entry) {
                fputcsv($handle, [
                    $entry['time'],
                    $entry['type'],
                    $entry['title'],
                    $entry['description'],
                    $entry['status'],
                    $entry['amount'],
                ]);
            }

            fclose($handle);
        }, $filename, [
            'Content-Type' => 'text/csv; charset=UTF-8',
        ]);
    }

    public function pdf(Request $request, CashierAuditReport $cashierAuditReport): Response
    {
        $report = $this->report($request, $cashierAuditReport);

        return Pdf::loadView('reports.cashier-audit-pdf', [
            'report' => $report,
        ])
            ->setPaper('a4')
            ->download("audit-kasir-{$report['cashier']['id']}-{$report['date']}.pdf");
    }

    /**
     * @return array<string, mixed>
     */
    private function report(Request $request, CashierAuditReport $cashierAuditReport): array
    {
        if (! $request->user()->canApproveSensitiveActions()) {
            abort(Response::HTTP_FORBIDDEN);
        }

        $validated = $request->validate([
            'cashier' => ['required', 'integer', 'exists:users,id'],
            'date' => ['nullable', 'date_format:Y-m-d'],
        ]);
        $cashier = User::query()
            ->where('role', UserRole::Cashier)
            ->findOrFail((int) $validated['cashier']);
        $date = CarbonImmutable::parse($validated['date'] ?? today())->toDateString();

        return $cashierAuditReport->forCashier($cashier, $date);
    }
}
