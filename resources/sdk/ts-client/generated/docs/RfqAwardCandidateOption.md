# RfqAwardCandidateOption

## Properties

| Name                      | Type                                                                |
| ------------------------- | ------------------------------------------------------------------- |
| `quoteId`                 | number                                                              |
| `quoteItemId`             | number                                                              |
| `supplierId`              | number                                                              |
| `supplierName`            | string                                                              |
| `unitPriceMinor`          | number                                                              |
| `unitPriceCurrency`       | string                                                              |
| `convertedUnitPriceMinor` | number                                                              |
| `convertedCurrency`       | string                                                              |
| `conversionUnavailable`   | boolean                                                             |
| `leadTimeDays`            | number                                                              |
| `quoteRevision`           | number                                                              |
| `quoteStatus`             | string                                                              |
| `submittedAt`             | Date                                                                |
| `award`                   | [RfqAwardCandidateExistingAward](RfqAwardCandidateExistingAward.md) |

## Example

```typescript
import type { RfqAwardCandidateOption } from '';

// TODO: Update the object below with actual values
const example = {
    quoteId: null,
    quoteItemId: null,
    supplierId: null,
    supplierName: null,
    unitPriceMinor: null,
    unitPriceCurrency: null,
    convertedUnitPriceMinor: null,
    convertedCurrency: null,
    conversionUnavailable: null,
    leadTimeDays: null,
    quoteRevision: null,
    quoteStatus: null,
    submittedAt: null,
    award: null,
} satisfies RfqAwardCandidateOption;

console.log(example);

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example);
console.log(exampleJSON);

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as RfqAwardCandidateOption;
console.log(exampleParsed);
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
