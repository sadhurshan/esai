# AdminPlansStoreRequest

## Properties

| Name                   | Type    |
| ---------------------- | ------- |
| `code`                 | string  |
| `name`                 | string  |
| `priceUsd`             | number  |
| `rfqsPerMonth`         | number  |
| `usersMax`             | number  |
| `analyticsEnabled`     | boolean |
| `inventoryEnabled`     | boolean |
| `multiCurrencyEnabled` | boolean |

## Example

```typescript
import type { AdminPlansStoreRequest } from '';

// TODO: Update the object below with actual values
const example = {
    code: null,
    name: null,
    priceUsd: null,
    rfqsPerMonth: null,
    usersMax: null,
    analyticsEnabled: null,
    inventoryEnabled: null,
    multiCurrencyEnabled: null,
} satisfies AdminPlansStoreRequest;

console.log(example);

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example);
console.log(exampleJSON);

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as AdminPlansStoreRequest;
console.log(exampleParsed);
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
