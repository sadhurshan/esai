# CreditNote

## Properties

| Name               | Type                                                        |
| ------------------ | ----------------------------------------------------------- |
| `id`               | string                                                      |
| `companyId`        | number                                                      |
| `invoiceId`        | number                                                      |
| `purchaseOrderId`  | number                                                      |
| `grnId`            | number                                                      |
| `creditNumber`     | string                                                      |
| `currency`         | string                                                      |
| `amount`           | string                                                      |
| `amountMinor`      | number                                                      |
| `reason`           | string                                                      |
| `status`           | string                                                      |
| `reviewComment`    | string                                                      |
| `issuedBy`         | number                                                      |
| `approvedBy`       | number                                                      |
| `approvedAt`       | Date                                                        |
| `attachments`      | [Array&lt;DocumentAttachment&gt;](DocumentAttachment.md)    |
| `lines`            | [Array&lt;CreditNoteLine&gt;](CreditNoteLine.md)            |
| `invoice`          | [CreditNoteInvoice](CreditNoteInvoice.md)                   |
| `purchaseOrder`    | [CreditNotePurchaseOrder](CreditNotePurchaseOrder.md)       |
| `goodsReceiptNote` | [CreditNoteGoodsReceiptNote](CreditNoteGoodsReceiptNote.md) |
| `createdAt`        | Date                                                        |
| `updatedAt`        | Date                                                        |

## Example

```typescript
import type { CreditNote } from '';

// TODO: Update the object below with actual values
const example = {
    id: null,
    companyId: null,
    invoiceId: null,
    purchaseOrderId: null,
    grnId: null,
    creditNumber: null,
    currency: null,
    amount: null,
    amountMinor: null,
    reason: null,
    status: null,
    reviewComment: null,
    issuedBy: null,
    approvedBy: null,
    approvedAt: null,
    attachments: null,
    lines: null,
    invoice: null,
    purchaseOrder: null,
    goodsReceiptNote: null,
    createdAt: null,
    updatedAt: null,
} satisfies CreditNote;

console.log(example);

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example);
console.log(exampleJSON);

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as CreditNote;
console.log(exampleParsed);
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
