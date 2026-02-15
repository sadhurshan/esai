# CompanyAddress

## Properties

| Name         | Type   |
| ------------ | ------ |
| `attention`  | string |
| `line1`      | string |
| `line2`      | string |
| `city`       | string |
| `state`      | string |
| `postalCode` | string |
| `country`    | string |

## Example

```typescript
import type { CompanyAddress } from '';

// TODO: Update the object below with actual values
const example = {
    attention: null,
    line1: null,
    line2: null,
    city: null,
    state: null,
    postalCode: null,
    country: null,
} satisfies CompanyAddress;

console.log(example);

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example);
console.log(exampleJSON);

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as CompanyAddress;
console.log(exampleParsed);
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
