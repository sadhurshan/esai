@component('mail::message')
# RFQ Lines Awarded Elsewhere

RFQ {{ $notification->meta['rfq_id'] ?? $notification->entity_id }} has awarded select line items to another supplier.

@if(!empty($notification->meta['lost_rfq_item_ids']))
@component('mail::panel')
**Lost Line Items:** {{ implode(', ', $notification->meta['lost_rfq_item_ids']) }}
@endcomponent
@endif

Thanks,
{{ config('app.name') }}
@endcomponent
