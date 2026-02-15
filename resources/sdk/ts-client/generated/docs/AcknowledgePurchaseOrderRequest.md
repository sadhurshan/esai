# AcknowledgePurchaseOrderRequest

## Properties

| Name       | Type   |
| ---------- | ------ |
| `decision` | string |
| `reason`   | string |

## Example

```typescript
import type { AcknowledgePurchaseOrderRequest } from '';

// TODO: Update the object below with actual values
const example = {
    decision: null,
    reason: null,
} satisfies AcknowledgePurchaseOrderRequest;

console.log(example);

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example);
console.log(exampleJSON);

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(
    exampleJSON,
) as AcknowledgePurchaseOrderRequest;
console.log(exampleParsed);
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
