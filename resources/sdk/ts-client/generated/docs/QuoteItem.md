
# QuoteItem


## Properties

Name | Type
------------ | -------------
`id` | string
`quoteId` | string
`rfqItemId` | string
`description` | string
`quantity` | number
`uom` | string
`unitPrice` | string
`unitPriceMinor` | number
`lineTotal` | string
`lineTotalMinor` | number

## Example

```typescript
import type { QuoteItem } from ''

// TODO: Update the object below with actual values
const example = {
  "id": null,
  "quoteId": null,
  "rfqItemId": null,
  "description": null,
  "quantity": null,
  "uom": null,
  "unitPrice": null,
  "unitPriceMinor": null,
  "lineTotal": null,
  "lineTotalMinor": null,
} satisfies QuoteItem

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as QuoteItem
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


