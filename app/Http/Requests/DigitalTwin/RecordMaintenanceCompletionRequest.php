<?php

namespace App\Http\Requests\DigitalTwin;

use App\Models\Asset;
use Illuminate\Foundation\Http\FormRequest;

class RecordMaintenanceCompletionRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var Asset|null $asset */
        $asset = $this->route('asset');

        return $asset !== null && $this->user()?->can('update', $asset);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'completed_at' => ['required', 'date'],
        ];
    }
}
