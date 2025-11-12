
# PurchaseOrderLine


## Properties

Name | Type
------------ | -------------
`id` | string
`lineNo` | number
`description` | string
`quantity` | number
`uom` | string
`currency` | string
`unitPrice` | number
`unitPriceMinor` | number
`lineSubtotal` | number
`lineSubtotalMinor` | number
`taxTotal` | number
`taxTotalMinor` | number
`lineTotal` | number
`lineTotalMinor` | number
`deliveryDate` | Date
`taxes` | [Array&lt;PurchaseOrderLineTaxesInner&gt;](PurchaseOrderLineTaxesInner.md)

## Example

```typescript
import type { PurchaseOrderLine } from ''

// TODO: Update the object below with actual values
const example = {
  "id": null,
  "lineNo": null,
  "description": null,
  "quantity": null,
  "uom": null,
  "currency": null,
  "unitPrice": null,
  "unitPriceMinor": null,
  "lineSubtotal": null,
  "lineSubtotalMinor": null,
  "taxTotal": null,
  "taxTotalMinor": null,
  "lineTotal": null,
  "lineTotalMinor": null,
  "deliveryDate": null,
  "taxes": null,
} satisfies PurchaseOrderLine

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as PurchaseOrderLine
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


