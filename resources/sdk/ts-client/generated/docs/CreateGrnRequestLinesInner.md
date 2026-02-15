# CreateGrnRequestLinesInner

## Properties

| Name                  | Type   |
| --------------------- | ------ |
| `purchaseOrderLineId` | number |
| `quantityReceived`    | number |
| `quantityAccepted`    | number |
| `quantityRejected`    | number |
| `rejectionReason`     | string |

## Example

```typescript
import type { CreateGrnRequestLinesInner } from '';

// TODO: Update the object below with actual values
const example = {
    purchaseOrderLineId: null,
    quantityReceived: null,
    quantityAccepted: null,
    quantityRejected: null,
    rejectionReason: null,
} satisfies CreateGrnRequestLinesInner;

console.log(example);

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example);
console.log(exampleJSON);

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as CreateGrnRequestLinesInner;
console.log(exampleParsed);
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
