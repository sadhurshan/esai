# QuoteLineUpdateRequest

## Properties

| Name             | Type                |
| ---------------- | ------------------- |
| `unitPriceMinor` | number              |
| `unitPrice`      | number              |
| `currency`       | string              |
| `leadTimeDays`   | number              |
| `note`           | string              |
| `taxCodeIds`     | Array&lt;number&gt; |
| `status`         | string              |

## Example

```typescript
import type { QuoteLineUpdateRequest } from '';

// TODO: Update the object below with actual values
const example = {
    unitPriceMinor: null,
    unitPrice: null,
    currency: null,
    leadTimeDays: null,
    note: null,
    taxCodeIds: null,
    status: null,
} satisfies QuoteLineUpdateRequest;

console.log(example);

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example);
console.log(exampleJSON);

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as QuoteLineUpdateRequest;
console.log(exampleParsed);
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
