# AdminPlansUpdateRequest

## Properties

| Name                   | Type    |
| ---------------------- | ------- |
| `name`                 | string  |
| `priceUsd`             | number  |
| `analyticsEnabled`     | boolean |
| `inventoryEnabled`     | boolean |
| `multiCurrencyEnabled` | boolean |

## Example

```typescript
import type { AdminPlansUpdateRequest } from '';

// TODO: Update the object below with actual values
const example = {
    name: null,
    priceUsd: null,
    analyticsEnabled: null,
    inventoryEnabled: null,
    multiCurrencyEnabled: null,
} satisfies AdminPlansUpdateRequest;

console.log(example);

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example);
console.log(exampleJSON);

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as AdminPlansUpdateRequest;
console.log(exampleParsed);
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
