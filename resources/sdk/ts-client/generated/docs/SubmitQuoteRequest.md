
# SubmitQuoteRequest


## Properties

Name | Type
------------ | -------------
`rfqId` | string
`supplierId` | string
`currency` | string
`leadTimeDays` | number
`minOrderQty` | number
`note` | string
`status` | string
`items` | [Array&lt;SubmitQuoteRequestItemsInner&gt;](SubmitQuoteRequestItemsInner.md)
`attachments` | Array&lt;string&gt;

## Example

```typescript
import type { SubmitQuoteRequest } from ''

// TODO: Update the object below with actual values
const example = {
  "rfqId": null,
  "supplierId": null,
  "currency": null,
  "leadTimeDays": null,
  "minOrderQty": null,
  "note": null,
  "status": null,
  "items": null,
  "attachments": null,
} satisfies SubmitQuoteRequest

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as SubmitQuoteRequest
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


