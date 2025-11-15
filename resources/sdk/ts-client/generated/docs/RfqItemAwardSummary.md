
# RfqItemAwardSummary


## Properties

Name | Type
------------ | -------------
`id` | number
`rfqItemId` | number
`supplierId` | number
`supplierName` | string
`quoteId` | number
`quoteItemId` | number
`poId` | number
`awardedQty` | number
`status` | string
`awardedAt` | Date

## Example

```typescript
import type { RfqItemAwardSummary } from ''

// TODO: Update the object below with actual values
const example = {
  "id": null,
  "rfqItemId": null,
  "supplierId": null,
  "supplierName": null,
  "quoteId": null,
  "quoteItemId": null,
  "poId": null,
  "awardedQty": null,
  "status": null,
  "awardedAt": null,
} satisfies RfqItemAwardSummary

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as RfqItemAwardSummary
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


