
# Invoice


## Properties

Name | Type
------------ | -------------
`id` | string
`companyId` | number
`purchaseOrderId` | number
`supplierId` | number
`invoiceNumber` | string
`currency` | string
`status` | string
`subtotal` | number
`taxAmount` | number
`total` | number
`matchSummary` | [InvoiceMatchSummary](InvoiceMatchSummary.md)
`document` | [InvoiceDocument](InvoiceDocument.md)
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
  "currency": null,
  "status": null,
  "subtotal": null,
  "taxAmount": null,
  "total": null,
  "matchSummary": null,
  "document": null,
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


