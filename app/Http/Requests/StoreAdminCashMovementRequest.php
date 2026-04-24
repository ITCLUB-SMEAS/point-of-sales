<?php

namespace App\Http\Requests;

use App\Enums\CashMovementType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreAdminCashMovementRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->canApproveSensitiveActions() ?? false;
    }

    /**
     * @return array<string, list<mixed>>
     */
    public function rules(): array
    {
        return [
            'cashier_shift_id' => ['required', 'integer', 'exists:cashier_shifts,id'],
            'type' => ['required', Rule::enum(CashMovementType::class)],
            'amount' => ['required', 'integer', 'min:1'],
            'category' => ['nullable', 'string', 'max:100'],
            'description' => ['required', 'string', 'max:1000'],
        ];
    }
}
