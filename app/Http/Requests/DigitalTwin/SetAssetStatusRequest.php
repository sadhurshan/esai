<?php

namespace App\Http\Requests\DigitalTwin;

use App\Models\Asset;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class SetAssetStatusRequest extends FormRequest
{
    private const STATUSES = ['active', 'standby', 'retired', 'maintenance'];

    public function authorize(): bool
    {
        /** @var Asset|null $asset */
        $asset = $this->route('asset');

        return $asset !== null && $this->user()?->can('setStatus', $asset);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', 'string', Rule::in(self::STATUSES)],
        ];
    }
}
