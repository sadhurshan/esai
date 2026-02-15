# GoodsReceiptNote

## Properties

| Name                  | Type                                                      |
| --------------------- | --------------------------------------------------------- |
| `id`                  | number                                                    |
| `companyId`           | number                                                    |
| `purchaseOrderId`     | number                                                    |
| `purchaseOrderNumber` | string                                                    |
| `poNumber`            | string                                                    |
| `grnNumber`           | string                                                    |
| `number`              | string                                                    |
| `status`              | string                                                    |
| `inspectedById`       | number                                                    |
| `inspectedAt`         | Date                                                      |
| `receivedAt`          | Date                                                      |
| `postedAt`            | Date                                                      |
| `reference`           | string                                                    |
| `notes`               | string                                                    |
| `supplierId`          | number                                                    |
| `supplierName`        | string                                                    |
| `inspector`           | [GoodsReceiptNoteInspector](GoodsReceiptNoteInspector.md) |
| `createdBy`           | [GoodsReceiptNoteInspector](GoodsReceiptNoteInspector.md) |
| `linesCount`          | number                                                    |
| `attachmentsCount`    | number                                                    |
| `lines`               | [Array&lt;GoodsReceiptLine&gt;](GoodsReceiptLine.md)      |
| `attachments`         | [Array&lt;DocumentAttachment&gt;](DocumentAttachment.md)  |
| `timeline`            | Array&lt;object&gt;                                       |
| `createdAt`           | Date                                                      |
| `updatedAt`           | Date                                                      |

## Example

```typescript
import type { GoodsReceiptNote } from '';

// TODO: Update the object below with actual values
const example = {
    id: null,
    companyId: null,
    purchaseOrderId: null,
    purchaseOrderNumber: null,
    poNumber: null,
    grnNumber: null,
    number: null,
    status: null,
    inspectedById: null,
    inspectedAt: null,
    receivedAt: null,
    postedAt: null,
    reference: null,
    notes: null,
    supplierId: null,
    supplierName: null,
    inspector: null,
    createdBy: null,
    linesCount: null,
    attachmentsCount: null,
    lines: null,
    attachments: null,
    timeline: null,
    createdAt: null,
    updatedAt: null,
} satisfies GoodsReceiptNote;

console.log(example);

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example);
console.log(exampleJSON);

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as GoodsReceiptNote;
console.log(exampleParsed);
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
