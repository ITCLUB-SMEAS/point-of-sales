<?php

namespace App\Http\Requests;

use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class CheckoutRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user() !== null;
    }

    protected function prepareForValidation(): void
    {
        $items = collect($this->input('items', []))
            ->filter(fn (array $item): bool => filled($item['product_id'] ?? null))
            ->map(function (array $item): array {
                if (($item['unit_price'] ?? '') === '') {
                    unset($item['unit_price']);
                }

                return $item;
            })
            ->values()
            ->all();

        $this->merge(['items' => $items]);
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'items' => ['required', 'array', 'min:1'],
            'items.*.product_id' => ['required', 'integer', 'exists:products,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1'],
            'items.*.unit_price' => ['nullable', 'integer', 'min:0'],
            'items.*.source_note' => ['nullable', 'string', 'max:100'],
            'payment_method' => ['required', Rule::in(['cash', 'qris'])],
            'payment_amount' => ['required', 'integer', 'min:1'],
            'payment_reference' => ['nullable', 'string', 'max:100'],
        ];
    }
}
