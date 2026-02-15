# SearchResult

## Properties

| Name       | Type   |
| ---------- | ------ |
| `id`       | string |
| `type`     | string |
| `title`    | string |
| `subtitle` | string |
| `snippet`  | string |
| `url`      | string |

## Example

```typescript
import type { SearchResult } from '';

// TODO: Update the object below with actual values
const example = {
    id: null,
    type: null,
    title: null,
    subtitle: null,
    snippet: null,
    url: null,
} satisfies SearchResult;

console.log(example);

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example);
console.log(exampleJSON);

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as SearchResult;
console.log(exampleParsed);
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
