# CreditNoteLine

## Properties

| Name                 | Type   |
| -------------------- | ------ |
| `id`                 | number |
| `invoiceLineId`      | number |
| `description`        | string |
| `qtyInvoiced`        | number |
| `qtyToCredit`        | number |
| `qtyAlreadyCredited` | number |
| `unitPriceMinor`     | number |
| `currency`           | string |
| `uom`                | string |
| `totalMinor`         | number |

## Example

```typescript
import type { CreditNoteLine } from '';

// TODO: Update the object below with actual values
const example = {
    id: null,
    invoiceLineId: null,
    description: null,
    qtyInvoiced: null,
    qtyToCredit: null,
    qtyAlreadyCredited: null,
    unitPriceMinor: null,
    currency: null,
    uom: null,
    totalMinor: null,
} satisfies CreditNoteLine;

console.log(example);

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example);
console.log(exampleJSON);

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as CreditNoteLine;
console.log(exampleParsed);
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
