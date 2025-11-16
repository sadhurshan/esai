
# CompanyGoodsReceiptLineInput


## Properties

Name | Type
------------ | -------------
`poLineId` | number
`qtyReceived` | number
`notes` | string
`uom` | string

## Example

```typescript
import type { CompanyGoodsReceiptLineInput } from ''

// TODO: Update the object below with actual values
const example = {
  "poLineId": null,
  "qtyReceived": null,
  "notes": null,
  "uom": null,
} satisfies CompanyGoodsReceiptLineInput

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as CompanyGoodsReceiptLineInput
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


