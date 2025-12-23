<?php

namespace App\Services\Ai\Workflow;

use App\Models\AiWorkflowStep;

class ReceivingQualityDraftConverter
{
    /**
     * Placeholder converter while the receiving-quality workflow is scaffolded.
     */
    public function convert(AiWorkflowStep $step): array
    {
        return [
            'workflow_id' => $step->workflow_id,
            'step_index' => $step->step_index,
            'status' => 'pending_implementation',
            'notes' => 'TODO: implement receiving + quality converter for approved outputs.',
        ];
    }
}
