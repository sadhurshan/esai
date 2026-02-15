# Quote

## Properties

| Name             | Type                                                           |
| ---------------- | -------------------------------------------------------------- |
| `id`             | string                                                         |
| `rfqId`          | number                                                         |
| `supplierId`     | number                                                         |
| `status`         | string                                                         |
| `currency`       | string                                                         |
| `unitPrice`      | number                                                         |
| `subtotal`       | string                                                         |
| `subtotalMinor`  | number                                                         |
| `taxAmount`      | string                                                         |
| `taxAmountMinor` | number                                                         |
| `total`          | string                                                         |
| `totalMinor`     | number                                                         |
| `minOrderQty`    | number                                                         |
| `leadTimeDays`   | number                                                         |
| `note`           | string                                                         |
| `revisionNo`     | number                                                         |
| `submittedBy`    | number                                                         |
| `submittedAt`    | Date                                                           |
| `withdrawnAt`    | Date                                                           |
| `withdrawReason` | string                                                         |
| `supplier`       | [GoodsReceiptNoteInspector](GoodsReceiptNoteInspector.md)      |
| `items`          | [Array&lt;QuoteItem&gt;](QuoteItem.md)                         |
| `attachments`    | [Array&lt;QuoteAttachmentsInner&gt;](QuoteAttachmentsInner.md) |
| `revisions`      | [Array&lt;QuoteRevision&gt;](QuoteRevision.md)                 |

## Example

```typescript
import type { Quote } from '';

// TODO: Update the object below with actual values
const example = {
    id: null,
    rfqId: null,
    supplierId: null,
    status: null,
    currency: null,
    unitPrice: null,
    subtotal: null,
    subtotalMinor: null,
    taxAmount: null,
    taxAmountMinor: null,
    total: null,
    totalMinor: null,
    minOrderQty: null,
    leadTimeDays: null,
    note: null,
    revisionNo: null,
    submittedBy: null,
    submittedAt: null,
    withdrawnAt: null,
    withdrawReason: null,
    supplier: null,
    items: null,
    attachments: null,
    revisions: null,
} satisfies Quote;

console.log(example);

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example);
console.log(exampleJSON);

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as Quote;
console.log(exampleParsed);
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
