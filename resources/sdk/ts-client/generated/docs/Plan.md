# Plan

## Properties

| Name                   | Type    |
| ---------------------- | ------- |
| `id`                   | number  |
| `code`                 | string  |
| `name`                 | string  |
| `priceUsd`             | number  |
| `rfqsPerMonth`         | number  |
| `usersMax`             | number  |
| `analyticsEnabled`     | boolean |
| `inventoryEnabled`     | boolean |
| `multiCurrencyEnabled` | boolean |
| `taxEngineEnabled`     | boolean |
| `localizationEnabled`  | boolean |
| `exportsEnabled`       | boolean |
| `createdAt`            | Date    |
| `updatedAt`            | Date    |

## Example

```typescript
import type { Plan } from '';

// TODO: Update the object below with actual values
const example = {
    id: null,
    code: null,
    name: null,
    priceUsd: null,
    rfqsPerMonth: null,
    usersMax: null,
    analyticsEnabled: null,
    inventoryEnabled: null,
    multiCurrencyEnabled: null,
    taxEngineEnabled: null,
    localizationEnabled: null,
    exportsEnabled: null,
    createdAt: null,
    updatedAt: null,
} satisfies Plan;

console.log(example);

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example);
console.log(exampleJSON);

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as Plan;
console.log(exampleParsed);
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
