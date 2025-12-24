<?php

namespace App\Services\Ai\Workflow;

use App\Models\AiWorkflowStep;

class PaymentProcessConverter
{
    public function convert(AiWorkflowStep $step): array
    {
        $payload = $this->extractPayload($step);

        $reference = $this->stringValue($payload['payment_reference'] ?? $payload['reference'] ?? null);
        $amount = $this->floatValue($payload['payment_amount'] ?? $payload['amount'] ?? null) ?? 0.0;
        $currency = $this->stringValue($payload['payment_currency'] ?? $payload['currency'] ?? 'USD');
        $normalizedCurrency = $currency !== null ? strtoupper($currency) : 'USD';

        return [
            'workflow_id' => $step->workflow_id,
            'step_index' => $step->step_index,
            'status' => AiWorkflowStep::APPROVAL_APPROVED,
            'payment_reference' => $reference,
            'amount' => round($amount, 2),
            'currency' => $normalizedCurrency,
            'notes' => $this->stringValue($payload['note'] ?? $payload['notes'] ?? null),
        ];
    }

    private function extractPayload(AiWorkflowStep $step): array
    {
        $output = is_array($step->output_json) ? $step->output_json : [];

        if (is_array($output['payload'] ?? null)) {
            return $output['payload'];
        }

        if ($output !== []) {
            return $output;
        }

        $draft = is_array($step->draft_json) ? $step->draft_json : [];

        return is_array($draft['payload'] ?? null) ? $draft['payload'] : $draft;
    }

    private function stringValue(mixed $value): ?string
    {
        if (is_string($value)) {
            $trimmed = trim($value);

            return $trimmed === '' ? null : $trimmed;
        }

        if (is_numeric($value)) {
            return (string) $value;
        }

        return null;
    }

    private function floatValue(mixed $value): ?float
    {
        if (is_float($value) || is_int($value)) {
            return (float) $value;
        }

        if (is_string($value) && is_numeric($value)) {
            return (float) $value;
        }

        return null;
    }
}
