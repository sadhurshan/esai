<?php

namespace App\Services\Ai\Policies;

final class PolicyDecision
{
    private bool $allowed = true;

    /**
     * @var list<string>
     */
    private array $reasons = [];

    /**
     * @var array<string, array{type:string,value:string,label:string|null}>
     */
    private array $requiredApprovals = [];

    /**
     * @var list<string>
     */
    private array $suggestedChanges = [];

    public function __construct(private readonly string $actionType, private readonly string $category)
    {
    }

    public function actionType(): string
    {
        return $this->actionType;
    }

    public function category(): string
    {
        return $this->category;
    }

    public function deny(string $reason, ?array $requiredApproval = null, ?string $suggestedChange = null): void
    {
        $this->allowed = false;
        $this->addReason($reason);

        if ($requiredApproval !== null) {
            $this->requireApproval($requiredApproval);
        }

        if ($suggestedChange !== null) {
            $this->addSuggestion($suggestedChange);
        }
    }

    public function addReason(string $reason): void
    {
        $trimmed = trim($reason);

        if ($trimmed === '' || in_array($trimmed, $this->reasons, true)) {
            return;
        }

        $this->reasons[] = $trimmed;
    }

    public function addSuggestion(string $suggestion): void
    {
        $trimmed = trim($suggestion);

        if ($trimmed === '' || in_array($trimmed, $this->suggestedChanges, true)) {
            return;
        }

        $this->suggestedChanges[] = $trimmed;
    }

    /**
     * @param array{type:string,value:string,label?:string|null} $approval
     */
    public function requireApproval(array $approval): void
    {
        $type = $approval['type'] ?? 'permission';
        $value = $approval['value'] ?? '';

        if ($value === '') {
            return;
        }

        $key = sprintf('%s:%s', $type, $value);

        if (! array_key_exists($key, $this->requiredApprovals)) {
            $this->requiredApprovals[$key] = [
                'type' => $type,
                'value' => $value,
                'label' => $approval['label'] ?? null,
            ];
        }
    }

    public function allowed(): bool
    {
        return $this->allowed;
    }

    /**
     * @return list<string>
     */
    public function reasons(): array
    {
        return $this->reasons;
    }

    /**
     * @return list<array{type:string,value:string,label:string|null}>
     */
    public function requiredApprovals(): array
    {
        return array_values($this->requiredApprovals);
    }

    /**
     * @return list<string>
     */
    public function suggestedChanges(): array
    {
        return $this->suggestedChanges;
    }

    /**
     * @return array{allowed:bool,reasons:list<string>,required_approvals:list<array{type:string,value:string,label:string|null}>,suggested_changes:list<string>}
     */
    public function toArray(): array
    {
        return [
            'allowed' => $this->allowed(),
            'reasons' => $this->reasons(),
            'required_approvals' => $this->requiredApprovals(),
            'suggested_changes' => $this->suggestedChanges(),
        ];
    }
}
