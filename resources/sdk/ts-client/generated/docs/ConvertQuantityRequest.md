# ConvertQuantityRequest

## Properties

| Name       | Type   |
| ---------- | ------ |
| `qty`      | number |
| `fromCode` | string |
| `toCode`   | string |

## Example

```typescript
import type { ConvertQuantityRequest } from '';

// TODO: Update the object below with actual values
const example = {
    qty: null,
    fromCode: null,
    toCode: null,
} satisfies ConvertQuantityRequest;

console.log(example);

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example);
console.log(exampleJSON);

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as ConvertQuantityRequest;
console.log(exampleParsed);
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
