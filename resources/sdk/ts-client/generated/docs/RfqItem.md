
# RfqItem


## Properties

Name | Type
------------ | -------------
`id` | string
`lineNo` | number
`partName` | string
`spec` | string
`method` | string
`material` | string
`tolerance` | string
`finish` | string
`quantity` | number
`uom` | string
`targetPrice` | number

## Example

```typescript
import type { RfqItem } from ''

// TODO: Update the object below with actual values
const example = {
  "id": null,
  "lineNo": null,
  "partName": null,
  "spec": null,
  "method": null,
  "material": null,
  "tolerance": null,
  "finish": null,
  "quantity": null,
  "uom": null,
  "targetPrice": null,
} satisfies RfqItem

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as RfqItem
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


