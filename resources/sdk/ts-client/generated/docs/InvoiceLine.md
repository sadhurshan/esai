# InvoiceLine

## Properties

| Name             | Type                                                           |
| ---------------- | -------------------------------------------------------------- |
| `id`             | string                                                         |
| `invoiceId`      | string                                                         |
| `poLineId`       | string                                                         |
| `description`    | string                                                         |
| `quantity`       | number                                                         |
| `uom`            | string                                                         |
| `currency`       | string                                                         |
| `unitPrice`      | number                                                         |
| `unitPriceMinor` | number                                                         |
| `lineSubtotal`   | number                                                         |
| `taxTotal`       | number                                                         |
| `lineTotal`      | number                                                         |
| `taxes`          | [Array&lt;InvoiceLineTaxesInner&gt;](InvoiceLineTaxesInner.md) |

## Example

```typescript
import type { InvoiceLine } from '';

// TODO: Update the object below with actual values
const example = {
    id: null,
    invoiceId: null,
    poLineId: null,
    description: null,
    quantity: null,
    uom: null,
    currency: null,
    unitPrice: null,
    unitPriceMinor: null,
    lineSubtotal: null,
    taxTotal: null,
    lineTotal: null,
    taxes: null,
} satisfies InvoiceLine;

console.log(example);

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example);
console.log(exampleJSON);

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as InvoiceLine;
console.log(exampleParsed);
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
