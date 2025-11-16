
# InvoiceLineInput


## Properties

Name | Type
------------ | -------------
`poLineId` | number
`quantity` | number
`unitPrice` | number
`description` | string
`uom` | string
`taxCodeIds` | Array&lt;number&gt;

## Example

```typescript
import type { InvoiceLineInput } from ''

// TODO: Update the object below with actual values
const example = {
  "poLineId": null,
  "quantity": null,
  "unitPrice": null,
  "description": null,
  "uom": null,
  "taxCodeIds": null,
} satisfies InvoiceLineInput

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as InvoiceLineInput
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


