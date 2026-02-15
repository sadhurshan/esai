# PoChangeOrder

## Properties

| Name               | Type                                                      |
| ------------------ | --------------------------------------------------------- |
| `id`               | number                                                    |
| `purchaseOrderId`  | number                                                    |
| `status`           | string                                                    |
| `reason`           | string                                                    |
| `poRevisionNo`     | number                                                    |
| `proposedByUserId` | number                                                    |
| `proposedAt`       | Date                                                      |
| `changesJson`      | object                                                    |
| `proposedByUser`   | [GoodsReceiptNoteInspector](GoodsReceiptNoteInspector.md) |

## Example

```typescript
import type { PoChangeOrder } from '';

// TODO: Update the object below with actual values
const example = {
    id: null,
    purchaseOrderId: null,
    status: null,
    reason: null,
    poRevisionNo: null,
    proposedByUserId: null,
    proposedAt: null,
    changesJson: null,
    proposedByUser: null,
} satisfies PoChangeOrder;

console.log(example);

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example);
console.log(exampleJSON);

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as PoChangeOrder;
console.log(exampleParsed);
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
