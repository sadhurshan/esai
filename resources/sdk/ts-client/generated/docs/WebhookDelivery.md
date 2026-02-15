# WebhookDelivery

## Properties

| Name             | Type   |
| ---------------- | ------ |
| `id`             | string |
| `subscriptionId` | string |
| `companyId`      | number |
| `event`          | string |
| `status`         | string |
| `attempts`       | number |
| `lastError`      | string |
| `dispatchedAt`   | Date   |
| `deliveredAt`    | Date   |
| `createdAt`      | Date   |

## Example

```typescript
import type { WebhookDelivery } from '';

// TODO: Update the object below with actual values
const example = {
    id: null,
    subscriptionId: null,
    companyId: null,
    event: null,
    status: null,
    attempts: null,
    lastError: null,
    dispatchedAt: null,
    deliveredAt: null,
    createdAt: null,
} satisfies WebhookDelivery;

console.log(example);

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example);
console.log(exampleJSON);

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as WebhookDelivery;
console.log(exampleParsed);
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
