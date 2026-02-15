# AdminCreateRateLimitRequest

## Properties

| Name            | Type    |
| --------------- | ------- |
| `companyId`     | number  |
| `scope`         | string  |
| `windowSeconds` | number  |
| `maxRequests`   | number  |
| `active`        | boolean |

## Example

```typescript
import type { AdminCreateRateLimitRequest } from '';

// TODO: Update the object below with actual values
const example = {
    companyId: null,
    scope: null,
    windowSeconds: null,
    maxRequests: null,
    active: null,
} satisfies AdminCreateRateLimitRequest;

console.log(example);

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example);
console.log(exampleJSON);

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as AdminCreateRateLimitRequest;
console.log(exampleParsed);
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
