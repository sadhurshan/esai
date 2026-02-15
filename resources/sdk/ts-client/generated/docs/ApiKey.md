# ApiKey

## Properties

| Name          | Type                |
| ------------- | ------------------- |
| `id`          | number              |
| `companyId`   | number              |
| `ownerUserId` | number              |
| `name`        | string              |
| `tokenPrefix` | string              |
| `scopes`      | Array&lt;string&gt; |
| `active`      | boolean             |
| `lastUsedAt`  | Date                |
| `expiresAt`   | Date                |
| `createdAt`   | Date                |
| `updatedAt`   | Date                |

## Example

```typescript
import type { ApiKey } from '';

// TODO: Update the object below with actual values
const example = {
    id: null,
    companyId: null,
    ownerUserId: null,
    name: null,
    tokenPrefix: null,
    scopes: null,
    active: null,
    lastUsedAt: null,
    expiresAt: null,
    createdAt: null,
    updatedAt: null,
} satisfies ApiKey;

console.log(example);

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example);
console.log(exampleJSON);

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as ApiKey;
console.log(exampleParsed);
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
