<?php

namespace App\Services;

use App\Enums\ApprovalAction;
use App\Models\ApprovalRequest;
use App\Models\AuditLog;
use App\Models\CashMovement;
use App\Models\HeldCart;
use App\Models\SaleTransaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Collection;

class CashierAuditReport
{
    /**
     * @return array{
     *     date: string,
     *     cashier: array{id: int, name: string},
     *     summary: array{transactions: int, approval_requests: int, cash_movements: int, drafts: int, transaction_total: int, cash_movement_total: int},
     *     entries: array<int, array{time: string, datetime: string, type: string, title: string, description: string, amount: ?int, status: ?string, reference: ?string}>
     * }
     */
    public function forCashier(User $cashier, string $date): array
    {
        $reportDate = CarbonImmutable::parse($date)->startOfDay();
        $start = $reportDate->startOfDay();
        $end = $reportDate->endOfDay();

        $transactions = SaleTransaction::query()
            ->where('cashier_id', $cashier->id)
            ->whereBetween('created_at', [$start, $end])
            ->latest('id')
            ->get();
        $approvalRequests = ApprovalRequest::query()
            ->where('requested_by', $cashier->id)
            ->whereIn('action', [
                ApprovalAction::VoidTransaction,
                ApprovalAction::RefundTransaction,
                ApprovalAction::PartialRefund,
            ])
            ->whereBetween('created_at', [$start, $end])
            ->latest('id')
            ->get();
        $cashMovements = CashMovement::query()
            ->where('user_id', $cashier->id)
            ->whereBetween('created_at', [$start, $end])
            ->latest('id')
            ->get();
        $draftAuditLogs = AuditLog::query()
            ->where('user_id', $cashier->id)
            ->whereIn('event', ['held_cart.created', 'held_cart.deleted'])
            ->whereBetween('created_at', [$start, $end])
            ->latest('id')
            ->get();
        $auditedDraftIds = $draftAuditLogs
            ->where('auditable_type', HeldCart::class)
            ->pluck('auditable_id')
            ->filter()
            ->unique()
            ->all();
        $drafts = HeldCart::query()
            ->where('user_id', $cashier->id)
            ->when($auditedDraftIds !== [], fn ($query) => $query->whereNotIn('id', $auditedDraftIds))
            ->whereBetween('created_at', [$start, $end])
            ->latest('id')
            ->get();

        return [
            'date' => $reportDate->toDateString(),
            'cashier' => [
                'id' => $cashier->id,
                'name' => $cashier->name,
            ],
            'summary' => [
                'transactions' => $transactions->count(),
                'approval_requests' => $approvalRequests->count(),
                'cash_movements' => $cashMovements->count(),
                'drafts' => $draftAuditLogs->count() + $drafts->count(),
                'transaction_total' => (int) $transactions->sum('total'),
                'cash_movement_total' => (int) $cashMovements->sum('amount'),
            ],
            'entries' => $this
                ->transactionEntries($transactions)
                ->merge($this->approvalEntries($approvalRequests))
                ->merge($this->cashMovementEntries($cashMovements))
                ->merge($this->draftAuditEntries($draftAuditLogs))
                ->merge($this->draftEntries($drafts))
                ->sortByDesc('datetime')
                ->values()
                ->all(),
        ];
    }

    /**
     * @param  Collection<int, SaleTransaction>  $transactions
     * @return Collection<int, array{time: string, datetime: string, type: string, title: string, description: string, amount: ?int, status: ?string, reference: ?string}>
     */
    private function transactionEntries(Collection $transactions): Collection
    {
        return $transactions->toBase()->map(fn (SaleTransaction $transaction): array => [
            'time' => $transaction->created_at->format('H:i'),
            'datetime' => $transaction->created_at->toDateTimeString(),
            'type' => 'transaction',
            'title' => 'Transaksi selesai',
            'description' => $transaction->number,
            'amount' => $transaction->total,
            'status' => $transaction->status->value,
            'reference' => route('transactions.receipt', $transaction),
        ]);
    }

    /**
     * @param  Collection<int, ApprovalRequest>  $approvalRequests
     * @return Collection<int, array{time: string, datetime: string, type: string, title: string, description: string, amount: ?int, status: ?string, reference: ?string}>
     */
    private function approvalEntries(Collection $approvalRequests): Collection
    {
        return $approvalRequests->toBase()->map(fn (ApprovalRequest $approvalRequest): array => [
            'time' => $approvalRequest->created_at->format('H:i'),
            'datetime' => $approvalRequest->created_at->toDateTimeString(),
            'type' => 'approval',
            'title' => $this->approvalTitle($approvalRequest->action),
            'description' => $approvalRequest->reason ?: 'Tanpa alasan',
            'amount' => null,
            'status' => $approvalRequest->status->value,
            'reference' => null,
        ]);
    }

    /**
     * @param  Collection<int, CashMovement>  $cashMovements
     * @return Collection<int, array{time: string, datetime: string, type: string, title: string, description: string, amount: ?int, status: ?string, reference: ?string}>
     */
    private function cashMovementEntries(Collection $cashMovements): Collection
    {
        return $cashMovements->toBase()->map(fn (CashMovement $cashMovement): array => [
            'time' => $cashMovement->created_at->format('H:i'),
            'datetime' => $cashMovement->created_at->toDateTimeString(),
            'type' => 'cash',
            'title' => 'Kas keluar / setoran',
            'description' => trim(($cashMovement->category ? $cashMovement->category.' · ' : '').$cashMovement->description),
            'amount' => $cashMovement->amount,
            'status' => $cashMovement->status->value,
            'reference' => null,
        ]);
    }

    /**
     * @param  Collection<int, AuditLog>  $draftAuditLogs
     * @return Collection<int, array{time: string, datetime: string, type: string, title: string, description: string, amount: ?int, status: ?string, reference: ?string}>
     */
    private function draftAuditEntries(Collection $draftAuditLogs): Collection
    {
        return $draftAuditLogs->toBase()->map(function (AuditLog $auditLog): array {
            $properties = $auditLog->properties ?? [];
            $name = (string) ($properties['name'] ?? 'Draft keranjang');
            $action = $auditLog->event === 'held_cart.deleted' ? 'dihapus' : 'dibuat';

            return [
                'time' => $auditLog->created_at->format('H:i'),
                'datetime' => $auditLog->created_at->toDateTimeString(),
                'type' => 'draft',
                'title' => 'Draft keranjang',
                'description' => "{$name} {$action}",
                'amount' => (int) ($properties['total'] ?? 0),
                'status' => $action,
                'reference' => null,
            ];
        });
    }

    /**
     * @param  Collection<int, HeldCart>  $drafts
     * @return Collection<int, array{time: string, datetime: string, type: string, title: string, description: string, amount: ?int, status: ?string, reference: ?string}>
     */
    private function draftEntries(Collection $drafts): Collection
    {
        return $drafts->toBase()->map(fn (HeldCart $draft): array => [
            'time' => $draft->created_at->format('H:i'),
            'datetime' => $draft->created_at->toDateTimeString(),
            'type' => 'draft',
            'title' => 'Draft keranjang',
            'description' => $draft->name,
            'amount' => $draft->total,
            'status' => 'active',
            'reference' => null,
        ]);
    }

    private function approvalTitle(ApprovalAction $action): string
    {
        return match ($action) {
            ApprovalAction::VoidTransaction => 'Request void transaksi',
            ApprovalAction::RefundTransaction, ApprovalAction::PartialRefund => 'Request refund transaksi',
            default => 'Request approval',
        };
    }
}
