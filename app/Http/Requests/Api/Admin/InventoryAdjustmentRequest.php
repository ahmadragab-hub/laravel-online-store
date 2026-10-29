<?php

namespace App\Http\Requests\Api\Admin;

use Illuminate\Foundation\Http\FormRequest;

class InventoryAdjustmentRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    public function rules(): array
    {
        return [
            'quantity_change' => [
                'required',
                'integer',
                'not_in:0',
            ],
            'reason' => [
                'required',
                'string',
                'max:500',
            ],
        ];
    }
}
