# RfqAwardCandidateLine

## Properties

| Name               | Type                                                               |
| ------------------ | ------------------------------------------------------------------ |
| `id`               | number                                                             |
| `lineNo`           | number                                                             |
| `partName`         | string                                                             |
| `spec`             | string                                                             |
| `quantity`         | number                                                             |
| `uom`              | string                                                             |
| `currency`         | string                                                             |
| `targetPriceMinor` | number                                                             |
| `candidates`       | [Array&lt;RfqAwardCandidateOption&gt;](RfqAwardCandidateOption.md) |
| `bestPrice`        | [RfqAwardCandidateBestPrice](RfqAwardCandidateBestPrice.md)        |

## Example

```typescript
import type { RfqAwardCandidateLine } from '';

// TODO: Update the object below with actual values
const example = {
    id: null,
    lineNo: null,
    partName: null,
    spec: null,
    quantity: null,
    uom: null,
    currency: null,
    targetPriceMinor: null,
    candidates: null,
    bestPrice: null,
} satisfies RfqAwardCandidateLine;

console.log(example);

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example);
console.log(exampleJSON);

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as RfqAwardCandidateLine;
console.log(exampleParsed);
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
