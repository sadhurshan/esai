
# QuoteLineRequest


## Properties

Name | Type
------------ | -------------
`rfqItemId` | string
`unitPriceMinor` | number
`unitPrice` | number
`currency` | string
`leadTimeDays` | number
`note` | string
`taxCodeIds` | Array&lt;number&gt;
`status` | string

## Example

```typescript
import type { QuoteLineRequest } from ''

// TODO: Update the object below with actual values
const example = {
  "rfqItemId": null,
  "unitPriceMinor": null,
  "unitPrice": null,
  "currency": null,
  "leadTimeDays": null,
  "note": null,
  "taxCodeIds": null,
  "status": null,
} satisfies QuoteLineRequest

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as QuoteLineRequest
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


