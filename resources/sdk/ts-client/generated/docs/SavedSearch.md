# SavedSearch

## Properties

| Name        | Type    |
| ----------- | ------- |
| `id`        | number  |
| `name`      | string  |
| `query`     | object  |
| `shared`    | boolean |
| `createdAt` | Date    |
| `updatedAt` | Date    |

## Example

```typescript
import type { SavedSearch } from '';

// TODO: Update the object below with actual values
const example = {
    id: null,
    name: null,
    query: null,
    shared: null,
    createdAt: null,
    updatedAt: null,
} satisfies SavedSearch;

console.log(example);

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example);
console.log(exampleJSON);

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as SavedSearch;
console.log(exampleParsed);
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
