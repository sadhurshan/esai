# PurchaseOrderEventActor

## Properties

| Name    | Type   |
| ------- | ------ |
| `id`    | number |
| `name`  | string |
| `email` | string |
| `type`  | string |

## Example

```typescript
import type { PurchaseOrderEventActor } from '';

// TODO: Update the object below with actual values
const example = {
    id: null,
    name: null,
    email: null,
    type: null,
} satisfies PurchaseOrderEventActor;

console.log(example);

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example);
console.log(exampleJSON);

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as PurchaseOrderEventActor;
console.log(exampleParsed);
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
