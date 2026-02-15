# CreateGrnRequest

## Properties

| Name          | Type                                                                     |
| ------------- | ------------------------------------------------------------------------ |
| `lines`       | [Array&lt;CreateGrnRequestLinesInner&gt;](CreateGrnRequestLinesInner.md) |
| `inspectedAt` | Date                                                                     |

## Example

```typescript
import type { CreateGrnRequest } from '';

// TODO: Update the object below with actual values
const example = {
    lines: null,
    inspectedAt: null,
} satisfies CreateGrnRequest;

console.log(example);

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example);
console.log(exampleJSON);

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as CreateGrnRequest;
console.log(exampleParsed);
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
