<?php

namespace App\Support\Audit;

use App\Models\AuditLog;
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

        AuditLog::create([
            'company_id' => $model->getAttribute('company_id') ?? $user?->company_id,
            'user_id' => $user?->id,
            'entity_type' => $model->getMorphClass(),
            'entity_id' => $model->getKey(),
            'action' => $action,
            'before' => $before,
            'after' => $after,
            'ip_address' => Request::ip(),
            'user_agent' => Request::header('User-Agent'),
        ]);
    }
}
