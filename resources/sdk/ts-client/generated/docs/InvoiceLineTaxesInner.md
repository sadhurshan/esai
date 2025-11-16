
# InvoiceLineTaxesInner


## Properties

Name | Type
------------ | -------------
`id` | number
`taxCodeId` | number
`ratePercent` | number
`amountMinor` | number
`amount` | number
`sequence` | number

## Example

```typescript
import type { InvoiceLineTaxesInner } from ''

// TODO: Update the object below with actual values
const example = {
  "id": null,
  "taxCodeId": null,
  "ratePercent": null,
  "amountMinor": null,
  "amount": null,
  "sequence": null,
} satisfies InvoiceLineTaxesInner

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as InvoiceLineTaxesInner
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


