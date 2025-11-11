<?php

namespace App\Http\Requests\Localization;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateUomRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $uomId = $this->route('uom')?->id;

        return [
            'name' => ['required', 'string', 'max:120'],
            'dimension' => ['required', Rule::in(['mass', 'length', 'volume', 'area', 'count', 'time', 'temperature', 'other'])],
            'symbol' => ['nullable', 'string', 'max:20'],
            'si_base' => ['required', 'boolean'],
            'code' => ['required', 'string', 'max:20', Rule::unique('uoms', 'code')->ignore($uomId)],
        ];
    }
}
