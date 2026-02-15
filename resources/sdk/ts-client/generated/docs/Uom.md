# Uom

## Properties

| Name        | Type    |
| ----------- | ------- |
| `code`      | string  |
| `name`      | string  |
| `dimension` | string  |
| `symbol`    | string  |
| `siBase`    | boolean |

## Example

```typescript
import type { Uom } from '';

// TODO: Update the object below with actual values
const example = {
    code: null,
    name: null,
    dimension: null,
    symbol: null,
    siBase: null,
} satisfies Uom;

console.log(example);

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example);
console.log(exampleJSON);

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as Uom;
console.log(exampleParsed);
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
