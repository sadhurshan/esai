# GoodsReceiptLine

## Properties

| Name                  | Type                                                     |
| --------------------- | -------------------------------------------------------- |
| `id`                  | number                                                   |
| `goodsReceiptNoteId`  | number                                                   |
| `purchaseOrderLineId` | number                                                   |
| `poLineId`            | number                                                   |
| `lineNo`              | string                                                   |
| `description`         | string                                                   |
| `orderedQty`          | number                                                   |
| `receivedQty`         | number                                                   |
| `acceptedQty`         | number                                                   |
| `rejectedQty`         | number                                                   |
| `previouslyReceived`  | number                                                   |
| `remainingQty`        | number                                                   |
| `defectNotes`         | string                                                   |
| `notes`               | string                                                   |
| `uom`                 | string                                                   |
| `unitPriceMinor`      | number                                                   |
| `currency`            | string                                                   |
| `variance`            | object                                                   |
| `attachments`         | [Array&lt;DocumentAttachment&gt;](DocumentAttachment.md) |

## Example

```typescript
import type { GoodsReceiptLine } from '';

// TODO: Update the object below with actual values
const example = {
    id: null,
    goodsReceiptNoteId: null,
    purchaseOrderLineId: null,
    poLineId: null,
    lineNo: null,
    description: null,
    orderedQty: null,
    receivedQty: null,
    acceptedQty: null,
    rejectedQty: null,
    previouslyReceived: null,
    remainingQty: null,
    defectNotes: null,
    notes: null,
    uom: null,
    unitPriceMinor: null,
    currency: null,
    variance: null,
    attachments: null,
} satisfies GoodsReceiptLine;

console.log(example);

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example);
console.log(exampleJSON);

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as GoodsReceiptLine;
console.log(exampleParsed);
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
