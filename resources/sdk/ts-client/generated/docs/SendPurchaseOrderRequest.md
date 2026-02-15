# SendPurchaseOrderRequest

## Properties

| Name      | Type                |
| --------- | ------------------- |
| `channel` | string              |
| `to`      | Array&lt;string&gt; |
| `cc`      | Array&lt;string&gt; |
| `message` | string              |

## Example

```typescript
import type { SendPurchaseOrderRequest } from '';

// TODO: Update the object below with actual values
const example = {
    channel: null,
    to: null,
    cc: null,
    message: null,
} satisfies SendPurchaseOrderRequest;

console.log(example);

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example);
console.log(exampleJSON);

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as SendPurchaseOrderRequest;
console.log(exampleParsed);
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
