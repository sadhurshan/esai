# FeatureFlag

## Properties

| Name        | Type    |
| ----------- | ------- |
| `id`        | number  |
| `companyId` | number  |
| `key`       | string  |
| `value`     | boolean |
| `createdAt` | Date    |
| `updatedAt` | Date    |

## Example

```typescript
import type { FeatureFlag } from '';

// TODO: Update the object below with actual values
const example = {
    id: null,
    companyId: null,
    key: null,
    value: null,
    createdAt: null,
    updatedAt: null,
} satisfies FeatureFlag;

console.log(example);

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example);
console.log(exampleJSON);

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as FeatureFlag;
console.log(exampleParsed);
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
