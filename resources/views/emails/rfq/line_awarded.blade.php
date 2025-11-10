@component('mail::message')
# RFQ Lines Awarded

RFQ {{ $notification->meta['rfq_id'] ?? $notification->entity_id }} has been partially awarded to your team.

@if(!empty($notification->meta['rfq_item_ids']))
@component('mail::panel')
**Awarded Line Items:** {{ implode(', ', $notification->meta['rfq_item_ids']) }}
@if(!empty($notification->meta['po_number']))

**Purchase Order:** {{ $notification->meta['po_number'] }}
@endif
@endcomponent
@endif

@component('mail::button', ['url' => url('/app/purchase-orders/'.($notification->meta['po_id'] ?? ''))])
View Purchase Order
@endcomponent

Thanks,
{{ config('app.name') }}
@endcomponent
