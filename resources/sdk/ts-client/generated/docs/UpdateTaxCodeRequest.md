# UpdateTaxCodeRequest

## Properties

| Name          | Type    |
| ------------- | ------- |
| `name`        | string  |
| `ratePercent` | number  |
| `isCompound`  | boolean |
| `active`      | boolean |

## Example

```typescript
import type { UpdateTaxCodeRequest } from '';

// TODO: Update the object below with actual values
const example = {
    name: null,
    ratePercent: null,
    isCompound: null,
    active: null,
} satisfies UpdateTaxCodeRequest;

console.log(example);

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example);
console.log(exampleJSON);

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as UpdateTaxCodeRequest;
console.log(exampleParsed);
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
