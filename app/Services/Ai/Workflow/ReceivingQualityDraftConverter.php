<?php

namespace App\Services\Ai\Workflow;

use App\Models\AiWorkflowStep;

class ReceivingQualityDraftConverter
{
    public function convert(AiWorkflowStep $step): array
    {
        $payload = $this->extractPayload($step);
        $receipts = $this->normalizeIdList($payload['receipts'] ?? $payload['items'] ?? null, 'receipt_id');

        if ($receipts === []) {
            $singleReceipt = $this->stringValue($payload['receipt_id'] ?? null);

            if ($singleReceipt !== null) {
                $receipts[] = $singleReceipt;
            }
        }

        $qualityFindings = $this->normalizeIdList($payload['quality_findings'] ?? $payload['issues'] ?? null, 'issue_code');
        $notes = $this->stringValue($payload['notes'] ?? null);

        return [
            'workflow_id' => $step->workflow_id,
            'step_index' => $step->step_index,
            'status' => AiWorkflowStep::APPROVAL_APPROVED,
            'receipts_reviewed' => $receipts,
            'quality_findings' => $qualityFindings,
            'notes' => $notes,
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

    private function normalizeIdList(mixed $value, string $keyField): array
    {
        if ($value === null) {
            return [];
        }

        if (! is_array($value)) {
            $items = [$value];
        } else {
            $items = array_is_list($value) ? $value : [$value];
        }
        $normalized = [];

        foreach ($items as $entry) {
            if (is_array($entry)) {
                $id = $this->stringValue($entry[$keyField] ?? reset($entry));
            } else {
                $id = $this->stringValue($entry);
            }

            if ($id !== null) {
                $normalized[] = $id;
            }

            if (count($normalized) >= 25) {
                break;
            }
        }

        return array_values(array_unique($normalized));
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
}
