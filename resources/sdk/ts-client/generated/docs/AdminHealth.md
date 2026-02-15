# AdminHealth

## Properties

| Name                       | Type    |
| -------------------------- | ------- |
| `appVersion`               | string  |
| `phpVersion`               | string  |
| `queueConnection`          | string  |
| `databaseConnected`        | boolean |
| `pendingWebhookDeliveries` | number  |

## Example

```typescript
import type { AdminHealth } from '';

// TODO: Update the object below with actual values
const example = {
    appVersion: null,
    phpVersion: null,
    queueConnection: null,
    databaseConnected: null,
    pendingWebhookDeliveries: null,
} satisfies AdminHealth;

console.log(example);

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example);
console.log(exampleJSON);

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as AdminHealth;
console.log(exampleParsed);
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
