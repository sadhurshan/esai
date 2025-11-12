
# CreditNote


## Properties

Name | Type
------------ | -------------
`id` | string
`companyId` | number
`invoiceId` | number
`purchaseOrderId` | number
`creditNumber` | string
`status` | string
`reason` | string
`total` | [Money](Money.md)
`balanceRemaining` | [Money](Money.md)
`reviewComment` | string
`issuedBy` | number
`approvedBy` | number
`issuedAt` | Date
`approvedAt` | Date
`createdAt` | Date
`updatedAt` | Date
`invoice` | [CreditNoteInvoice](CreditNoteInvoice.md)

## Example

```typescript
import type { CreditNote } from ''

// TODO: Update the object below with actual values
const example = {
  "id": null,
  "companyId": null,
  "invoiceId": null,
  "purchaseOrderId": null,
  "creditNumber": null,
  "status": null,
  "reason": null,
  "total": null,
  "balanceRemaining": null,
  "reviewComment": null,
  "issuedBy": null,
  "approvedBy": null,
  "issuedAt": null,
  "approvedAt": null,
  "createdAt": null,
  "updatedAt": null,
  "invoice": null,
} satisfies CreditNote

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as CreditNote
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


