
# CreateInvoiceFromPoRequestLinesInner


## Properties

Name | Type
------------ | -------------
`poLineId` | number
`quantity` | number
`qtyInvoiced` | number
`unitPrice` | number
`unitPriceMinor` | number
`description` | string
`uom` | string
`taxCodeIds` | Array&lt;number&gt;

## Example

```typescript
import type { CreateInvoiceFromPoRequestLinesInner } from ''

// TODO: Update the object below with actual values
const example = {
  "poLineId": null,
  "quantity": null,
  "qtyInvoiced": null,
  "unitPrice": null,
  "unitPriceMinor": null,
  "description": null,
  "uom": null,
  "taxCodeIds": null,
} satisfies CreateInvoiceFromPoRequestLinesInner

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as CreateInvoiceFromPoRequestLinesInner
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


