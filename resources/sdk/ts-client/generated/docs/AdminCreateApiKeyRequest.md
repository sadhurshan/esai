# AdminCreateApiKeyRequest

## Properties

| Name        | Type                |
| ----------- | ------------------- |
| `companyId` | number              |
| `name`      | string              |
| `scopes`    | Array&lt;string&gt; |
| `expiresAt` | Date                |

## Example

```typescript
import type { AdminCreateApiKeyRequest } from '';

// TODO: Update the object below with actual values
const example = {
    companyId: null,
    name: null,
    scopes: null,
    expiresAt: null,
} satisfies AdminCreateApiKeyRequest;

console.log(example);

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example);
console.log(exampleJSON);

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as AdminCreateApiKeyRequest;
console.log(exampleParsed);
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
