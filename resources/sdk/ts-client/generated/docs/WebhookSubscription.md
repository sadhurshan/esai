# WebhookSubscription

## Properties

| Name          | Type                |
| ------------- | ------------------- |
| `id`          | string              |
| `companyId`   | number              |
| `url`         | string              |
| `secretHint`  | string              |
| `events`      | Array&lt;string&gt; |
| `active`      | boolean             |
| `retryPolicy` | object              |
| `createdAt`   | Date                |
| `updatedAt`   | Date                |

## Example

```typescript
import type { WebhookSubscription } from '';

// TODO: Update the object below with actual values
const example = {
    id: null,
    companyId: null,
    url: null,
    secretHint: null,
    events: null,
    active: null,
    retryPolicy: null,
    createdAt: null,
    updatedAt: null,
} satisfies WebhookSubscription;

console.log(example);

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example);
console.log(exampleJSON);

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as WebhookSubscription;
console.log(exampleParsed);
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
