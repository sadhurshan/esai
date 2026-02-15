# CreateAwards200ResponseAllOfData

## Properties

| Name     | Type                                                       |
| -------- | ---------------------------------------------------------- |
| `awards` | [Array&lt;RfqItemAwardSummary&gt;](RfqItemAwardSummary.md) |

## Example

```typescript
import type { CreateAwards200ResponseAllOfData } from '';

// TODO: Update the object below with actual values
const example = {
    awards: null,
} satisfies CreateAwards200ResponseAllOfData;

console.log(example);

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example);
console.log(exampleJSON);

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(
    exampleJSON,
) as CreateAwards200ResponseAllOfData;
console.log(exampleParsed);
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
