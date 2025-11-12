
# CreateRfqRequestItemsInner


## Properties

Name | Type
------------ | -------------
`partName` | string
`spec` | string
`quantity` | number
`uom` | string
`targetPrice` | number

## Example

```typescript
import type { CreateRfqRequestItemsInner } from ''

// TODO: Update the object below with actual values
const example = {
  "partName": null,
  "spec": null,
  "quantity": null,
  "uom": null,
  "targetPrice": null,
} satisfies CreateRfqRequestItemsInner

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as CreateRfqRequestItemsInner
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


