
# RfqLinePayload


## Properties

Name | Type
------------ | -------------
`partName` | string
`spec` | string
`method` | string
`material` | string
`tolerance` | string
`finish` | string
`quantity` | number
`uom` | string
`targetPrice` | number
`notes` | string
`cadDocumentId` | string

## Example

```typescript
import type { RfqLinePayload } from ''

// TODO: Update the object below with actual values
const example = {
  "partName": null,
  "spec": null,
  "method": null,
  "material": null,
  "tolerance": null,
  "finish": null,
  "quantity": null,
  "uom": null,
  "targetPrice": null,
  "notes": null,
  "cadDocumentId": null,
} satisfies RfqLinePayload

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as RfqLinePayload
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


