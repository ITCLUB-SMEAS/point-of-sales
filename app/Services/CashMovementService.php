<?php

namespace App\Services;

use App\Enums\ApprovalAction;
use App\Enums\ApprovalStatus;
use App\Enums\CashMovementType;
use App\Enums\ShiftStatus;
use App\Models\ApprovalRequest;
use App\Models\AuditLog;
use App\Models\CashMovement;
use App\Models\CashierShift;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class CashMovementService
{
    /**
     * @param  array{cashier_shift_id?: int|null, type: CashMovementType|string, amount: int, category?: string|null, description?: string|null}  $payload
     */
    public function record(User $user, array $payload): CashMovement
    {
        $status = $user->canApproveSensitiveActions()
            ? ApprovalStatus::Approved
            : ApprovalStatus::Pending;

        $movement = DB::transaction(function () use ($user, $payload, $status): CashMovement {
            $shift = $this->resolveShift($user, $payload['cashier_shift_id'] ?? null);
            $movement = CashMovement::query()->create([
                'cashier_shift_id' => $shift->id,
                'user_id' => $user->id,
                'approved_by' => $status === ApprovalStatus::Approved ? $user->id : null,
                'type' => $payload['type'],
                'status' => $status,
                'amount' => (int) $payload['amount'],
                'category' => $payload['category'] ?? null,
                'description' => $payload['description'] ?? null,
                'occurred_at' => now(),
                'approved_at' => $status === ApprovalStatus::Approved ? now() : null,
            ]);

            if ($status === ApprovalStatus::Pending) {
                ApprovalRequest::query()->create([
                    'requested_by' => $user->id,
                    'approvable_type' => $movement->getMorphClass(),
                    'approvable_id' => $movement->id,
                    'action' => ApprovalAction::CashMovement,
                    'status' => ApprovalStatus::Pending,
                    'reason' => $movement->description,
                ]);

                $this->audit('cash_movement.requested', $user, $movement, [
                    'type' => $movement->type->value,
                    'amount' => $movement->amount,
                ]);

                return $movement;
            }

            $this->auditApproved($movement, $user);

            return $movement;
        });

        if ($movement->status === ApprovalStatus::Pending) {
            throw new AuthorizationException('Kas keluar atau setoran membutuhkan approval admin atau supervisor.');
        }

        return $movement;
    }

    public function approve(CashMovement $cashMovement, User $approvedBy): CashMovement
    {
        if (! $approvedBy->canApproveSensitiveActions()) {
            throw new AuthorizationException('Hanya admin atau supervisor yang bisa menyetujui kas.');
        }

        return DB::transaction(function () use ($cashMovement, $approvedBy): CashMovement {
            $cashMovement->refresh();

            if ($cashMovement->status === ApprovalStatus::Approved) {
                return $cashMovement;
            }

            $cashMovement->update([
                'status' => ApprovalStatus::Approved,
                'approved_by' => $approvedBy->id,
                'approved_at' => now(),
            ]);

            $this->auditApproved($cashMovement, $approvedBy);
            $this->recalculateClosedShift($cashMovement);

            return $cashMovement->fresh();
        });
    }

    private function resolveShift(User $user, ?int $cashierShiftId): CashierShift
    {
        $query = CashierShift::query()->lockForUpdate();

        if ($cashierShiftId !== null) {
            return $query->whereKey($cashierShiftId)->firstOrFail();
        }

        $shift = $query
            ->where('user_id', $user->id)
            ->where('status', ShiftStatus::Open)
            ->latest('id')
            ->first();

        if (! $shift instanceof CashierShift) {
            throw new InvalidArgumentException('Shift aktif tidak ditemukan untuk mencatat kas.');
        }

        return $shift;
    }

    private function recalculateClosedShift(CashMovement $cashMovement): void
    {
        $shift = $cashMovement->cashierShift;

        if (! $shift instanceof CashierShift || $shift->status !== ShiftStatus::Closed || $shift->closing_cash === null) {
            return;
        }

        $cashPayments = $shift->summary()['cash_revenue'];
        $cashOut = $shift->summary()['cash_expense_total'] + $shift->summary()['cash_deposit_total'];
        $expectedClosingCash = $shift->opening_cash + $cashPayments - $cashOut;

        $shift->update([
            'expected_closing_cash' => $expectedClosingCash,
            'cash_variance' => $shift->closing_cash - $expectedClosingCash,
        ]);
    }

    private function auditApproved(CashMovement $cashMovement, User $user): void
    {
        $this->audit('cash_movement.approved', $user, $cashMovement, [
            'type' => $cashMovement->type->value,
            'amount' => $cashMovement->amount,
        ]);
    }

    /**
     * @param  array<string, mixed>  $properties
     */
    private function audit(string $event, User $user, Model $auditable, array $properties = []): void
    {
        AuditLog::query()->create([
            'user_id' => $user->id,
            'auditable_type' => $auditable::class,
            'auditable_id' => $auditable->id,
            'event' => $event,
            'properties' => $properties,
        ]);
    }
}
