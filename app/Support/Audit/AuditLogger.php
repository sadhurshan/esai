<?php

namespace App\Support\Audit;

use App\Models\AuditLog;
use App\Support\ActivePersona;
use App\Support\ActivePersonaContext;
use App\Support\CompanyContext;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLogger
{
    public function created(Model $model, array $after = []): void
    {
        $this->log($model, 'created', null, $after ?: $model->toArray());
    }

    public function updated(Model $model, array $before = [], array $after = []): void
    {
        $this->log($model, 'updated', $before ?: $model->getOriginal(), $after ?: $model->getChanges());
    }

    public function deleted(Model $model, array $before = []): void
    {
        $this->log($model, 'deleted', $before ?: $model->toArray(), null);
    }

    public function custom(Model $model, string $event, ?array $context = null): void
    {
        $payload = array_filter([
            'event' => $event,
            'context' => $context,
        ], static fn ($value) => $value !== null && $value !== []);

        $this->log($model, 'updated', $payload, $payload);
    }

    protected function log(Model $model, string $action, ?array $before, ?array $after): void
    {
        $user = Auth::user();
        $persona = $this->resolvePersona($user);

        $personaType = $persona?->type();
        $personaCompanyId = $persona?->companyId();
        $actingSupplierId = $persona?->supplierId();

        if ($personaType === null && $user !== null) {
            $scopedCompanyId = $model->getAttribute('company_id')
                ?? CompanyContext::get()
                ?? $user->company_id;

            if ($scopedCompanyId !== null) {
                $personaType = ActivePersona::TYPE_BUYER;
                $personaCompanyId = (int) $scopedCompanyId;
            }
        }

        AuditLog::create([
            'company_id' => $model->getAttribute('company_id') ?? $user?->company_id,
            'user_id' => $user?->id,
            'persona_type' => $personaType,
            'persona_company_id' => $personaCompanyId,
            'acting_supplier_id' => $actingSupplierId,
            'entity_type' => $model->getMorphClass(),
            'entity_id' => $model->getKey(),
            'action' => $action,
            'before' => $before,
            'after' => $after,
            'ip_address' => Request::ip(),
            'user_agent' => Request::header('User-Agent'),
        ]);
    }

    private function resolvePersona(?Authenticatable $user): ?ActivePersona
    {
        $persona = ActivePersonaContext::get();

        if ($persona !== null) {
            return $persona;
        }

        $request = request();

        if ($request !== null) {
            $payload = $request->attributes->get('active_persona');

            if (is_array($payload)) {
                return ActivePersona::fromArray($payload);
            }
        }

        return null;
    }
}
