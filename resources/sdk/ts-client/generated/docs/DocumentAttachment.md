# DocumentAttachment

## Properties

| Name        | Type   |
| ----------- | ------ |
| `id`        | number |
| `filename`  | string |
| `mime`      | string |
| `sizeBytes` | number |
| `version`   | number |
| `createdAt` | Date   |
| `meta`      | object |

## Example

```typescript
import type { DocumentAttachment } from '';

// TODO: Update the object below with actual values
const example = {
    id: null,
    filename: null,
    mime: null,
    sizeBytes: null,
    version: null,
    createdAt: null,
    meta: null,
} satisfies DocumentAttachment;

console.log(example);

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example);
console.log(exampleJSON);

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as DocumentAttachment;
console.log(exampleParsed);
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
