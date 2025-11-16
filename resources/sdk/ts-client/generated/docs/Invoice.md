
# Invoice


## Properties

Name | Type
------------ | -------------
`id` | string
`companyId` | number
`purchaseOrderId` | number
`supplierId` | number
`invoiceNumber` | string
`invoiceDate` | Date
`currency` | string
`status` | string
`subtotal` | number
`taxAmount` | number
`total` | number
`matchSummary` | [InvoiceMatchSummary](InvoiceMatchSummary.md)
`supplier` | [InvoiceSupplier](InvoiceSupplier.md)
`purchaseOrder` | [CreditNotePurchaseOrder](CreditNotePurchaseOrder.md)
`lines` | [Array&lt;InvoiceLine&gt;](InvoiceLine.md)
`matches` | [Array&lt;InvoiceMatch&gt;](InvoiceMatch.md)
`document` | [InvoiceDocument](InvoiceDocument.md)
`attachments` | [Array&lt;DocumentAttachment&gt;](DocumentAttachment.md)
`createdAt` | Date
`updatedAt` | Date

## Example

```typescript
import type { Invoice } from ''

// TODO: Update the object below with actual values
const example = {
  "id": null,
  "companyId": null,
  "purchaseOrderId": null,
  "supplierId": null,
  "invoiceNumber": null,
  "invoiceDate": null,
  "currency": null,
  "status": null,
  "subtotal": null,
  "taxAmount": null,
  "total": null,
  "matchSummary": null,
  "supplier": null,
  "purchaseOrder": null,
  "lines": null,
  "matches": null,
  "document": null,
  "attachments": null,
  "createdAt": null,
  "updatedAt": null,
} satisfies Invoice

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as Invoice
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


