# QuoteItem

## Properties

| Name                | Type                                                       |
| ------------------- | ---------------------------------------------------------- |
| `id`                | string                                                     |
| `quoteId`           | string                                                     |
| `rfqItemId`         | string                                                     |
| `currency`          | string                                                     |
| `quantity`          | number                                                     |
| `unitPrice`         | number                                                     |
| `unitPriceMinor`    | number                                                     |
| `lineSubtotal`      | number                                                     |
| `lineSubtotalMinor` | number                                                     |
| `taxTotal`          | number                                                     |
| `taxTotalMinor`     | number                                                     |
| `lineTotal`         | number                                                     |
| `lineTotalMinor`    | number                                                     |
| `leadTimeDays`      | number                                                     |
| `note`              | string                                                     |
| `status`            | string                                                     |
| `taxes`             | [Array&lt;QuoteItemTaxesInner&gt;](QuoteItemTaxesInner.md) |

## Example

```typescript
import type { QuoteItem } from '';

// TODO: Update the object below with actual values
const example = {
    id: null,
    quoteId: null,
    rfqItemId: null,
    currency: null,
    quantity: null,
    unitPrice: null,
    unitPriceMinor: null,
    lineSubtotal: null,
    lineSubtotalMinor: null,
    taxTotal: null,
    taxTotalMinor: null,
    lineTotal: null,
    lineTotalMinor: null,
    leadTimeDays: null,
    note: null,
    status: null,
    taxes: null,
} satisfies QuoteItem;

console.log(example);

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example);
console.log(exampleJSON);

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as QuoteItem;
console.log(exampleParsed);
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
