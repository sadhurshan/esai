
# PurchaseOrder


## Properties

Name | Type
------------ | -------------
`id` | number
`companyId` | number
`poNumber` | string
`status` | string
`currency` | string
`incoterm` | string
`taxPercent` | number
`subtotal` | string
`subtotalMinor` | number
`taxAmount` | string
`taxAmountMinor` | number
`total` | string
`totalMinor` | number
`revisionNo` | number
`rfqId` | number
`quoteId` | number
`supplier` | [PurchaseOrderSupplier](PurchaseOrderSupplier.md)
`rfq` | [PurchaseOrderRfq](PurchaseOrderRfq.md)
`lines` | [Array&lt;PurchaseOrderLine&gt;](PurchaseOrderLine.md)
`changeOrders` | [Array&lt;PoChangeOrder&gt;](PoChangeOrder.md)
`createdAt` | Date
`updatedAt` | Date

## Example

```typescript
import type { PurchaseOrder } from ''

// TODO: Update the object below with actual values
const example = {
  "id": null,
  "companyId": null,
  "poNumber": null,
  "status": null,
  "currency": null,
  "incoterm": null,
  "taxPercent": null,
  "subtotal": null,
  "subtotalMinor": null,
  "taxAmount": null,
  "taxAmountMinor": null,
  "total": null,
  "totalMinor": null,
  "revisionNo": null,
  "rfqId": null,
  "quoteId": null,
  "supplier": null,
  "rfq": null,
  "lines": null,
  "changeOrders": null,
  "createdAt": null,
  "updatedAt": null,
} satisfies PurchaseOrder

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as PurchaseOrder
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


