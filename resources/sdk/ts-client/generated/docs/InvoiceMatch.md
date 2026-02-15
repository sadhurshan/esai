# InvoiceMatch

## Properties

| Name                 | Type   |
| -------------------- | ------ |
| `id`                 | number |
| `invoiceId`          | number |
| `purchaseOrderId`    | number |
| `goodsReceiptNoteId` | number |
| `result`             | string |
| `details`            | object |
| `createdAt`          | Date   |

## Example

```typescript
import type { InvoiceMatch } from '';

// TODO: Update the object below with actual values
const example = {
    id: null,
    invoiceId: null,
    purchaseOrderId: null,
    goodsReceiptNoteId: null,
    result: null,
    details: null,
    createdAt: null,
} satisfies InvoiceMatch;

console.log(example);

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example);
console.log(exampleJSON);

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as InvoiceMatch;
console.log(exampleParsed);
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
