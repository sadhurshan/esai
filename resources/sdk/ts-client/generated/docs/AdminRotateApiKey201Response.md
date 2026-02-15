# AdminRotateApiKey201Response

## Properties

| Name      | Type                                                                              |
| --------- | --------------------------------------------------------------------------------- |
| `status`  | string                                                                            |
| `message` | string                                                                            |
| `data`    | [AdminRotateApiKey201ResponseAllOfData](AdminRotateApiKey201ResponseAllOfData.md) |
| `meta`    | [RequestMeta](RequestMeta.md)                                                     |

## Example

```typescript
import type { AdminRotateApiKey201Response } from '';

// TODO: Update the object below with actual values
const example = {
    status: null,
    message: null,
    data: null,
    meta: null,
} satisfies AdminRotateApiKey201Response;

console.log(example);

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example);
console.log(exampleJSON);

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as AdminRotateApiKey201Response;
console.log(exampleParsed);
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
