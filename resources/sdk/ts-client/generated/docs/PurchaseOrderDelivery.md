# PurchaseOrderDelivery

## Properties

| Name                | Type                                                                      |
| ------------------- | ------------------------------------------------------------------------- |
| `id`                | number                                                                    |
| `purchaseOrderId`   | number                                                                    |
| `channel`           | string                                                                    |
| `status`            | string                                                                    |
| `recipientsTo`      | Array&lt;string&gt;                                                       |
| `recipientsCc`      | Array&lt;string&gt;                                                       |
| `message`           | string                                                                    |
| `deliveryReference` | string                                                                    |
| `responseMeta`      | object                                                                    |
| `errorReason`       | string                                                                    |
| `sentAt`            | Date                                                                      |
| `createdAt`         | Date                                                                      |
| `updatedAt`         | Date                                                                      |
| `sentBy`            | [PurchaseOrderLatestDeliverySentBy](PurchaseOrderLatestDeliverySentBy.md) |

## Example

```typescript
import type { PurchaseOrderDelivery } from '';

// TODO: Update the object below with actual values
const example = {
    id: null,
    purchaseOrderId: null,
    channel: null,
    status: null,
    recipientsTo: null,
    recipientsCc: null,
    message: null,
    deliveryReference: null,
    responseMeta: null,
    errorReason: null,
    sentAt: null,
    createdAt: null,
    updatedAt: null,
    sentBy: null,
} satisfies PurchaseOrderDelivery;

console.log(example);

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example);
console.log(exampleJSON);

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as PurchaseOrderDelivery;
console.log(exampleParsed);
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
