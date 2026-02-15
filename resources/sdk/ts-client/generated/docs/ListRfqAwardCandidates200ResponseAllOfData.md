# ListRfqAwardCandidates200ResponseAllOfData

## Properties

| Name              | Type                                                                                                |
| ----------------- | --------------------------------------------------------------------------------------------------- |
| `rfq`             | [ListRfqAwardCandidates200ResponseAllOfDataRfq](ListRfqAwardCandidates200ResponseAllOfDataRfq.md)   |
| `companyCurrency` | string                                                                                              |
| `lines`           | [Array&lt;RfqAwardCandidateLine&gt;](RfqAwardCandidateLine.md)                                      |
| `awards`          | [Array&lt;RfqItemAwardSummary&gt;](RfqItemAwardSummary.md)                                          |
| `meta`            | [ListRfqAwardCandidates200ResponseAllOfDataMeta](ListRfqAwardCandidates200ResponseAllOfDataMeta.md) |

## Example

```typescript
import type { ListRfqAwardCandidates200ResponseAllOfData } from '';

// TODO: Update the object below with actual values
const example = {
    rfq: null,
    companyCurrency: null,
    lines: null,
    awards: null,
    meta: null,
} satisfies ListRfqAwardCandidates200ResponseAllOfData;

console.log(example);

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example);
console.log(exampleJSON);

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(
    exampleJSON,
) as ListRfqAwardCandidates200ResponseAllOfData;
console.log(exampleParsed);
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
