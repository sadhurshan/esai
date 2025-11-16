<?php

namespace App\Http\Requests\Admin;

use App\Models\RoleTemplate;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateRoleTemplateRequest extends FormRequest
{
    public function authorize(): bool
    {
        /** @var RoleTemplate|null $role */
        $role = $this->route('roleTemplate');

        return $role !== null && ($this->user()?->can('update', $role) ?? false);
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $permissionKeys = collect(config('rbac.permission_groups', []))
            ->flatMap(fn (array $group) => collect($group['permissions'] ?? [])->pluck('key'))
            ->filter()
            ->values()
            ->all();

        return [
            'permissions' => ['required', 'array', 'min:1'],
            'permissions.*' => ['string', Rule::in($permissionKeys)],
        ];
    }
}
