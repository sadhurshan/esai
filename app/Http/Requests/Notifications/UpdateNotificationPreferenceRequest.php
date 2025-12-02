<?php

namespace App\Http\Requests\Notifications;

use App\Http\Requests\ApiFormRequest;
use Illuminate\Validation\Rule;

class UpdateNotificationPreferenceRequest extends ApiFormRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $eventTypes = [
            'rfq_created',
            'quote_submitted',
            'po_issued',
            'grn_posted',
            'invoice_created',
            'invoice_status_changed',
            'rfq.clarification.question',
            'rfq.clarification.answer',
            'rfq.clarification.amendment',
            'rfq.deadline.extended',
            'quote.revision.submitted',
            'quote.withdrawn',
            'rfq_line_awarded',
            'rfq_line_lost',
            'plan_overlimit',
            'certificate_expiry',
            'analytics_query',
            'approvals.pending',
            'rma.raised',
            'rma.reviewed',
            'rma.closed',
        ];

        return [
            'event_type' => ['required', 'string', Rule::in($eventTypes)],
            'channel' => ['required', 'string', Rule::in(['push', 'email', 'both'])],
            'digest' => ['required', 'string', Rule::in(['none', 'daily', 'weekly'])],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function payload(): array
    {
        return $this->validated();
    }
}
