# SubmitQuoteRevisionRequestItemsInner

## Properties

| Name             | Type   |
| ---------------- | ------ |
| `rfqItemId`      | string |
| `quantity`       | number |
| `unitPriceMinor` | number |

## Example

```typescript
import type { SubmitQuoteRevisionRequestItemsInner } from '';

// TODO: Update the object below with actual values
const example = {
    rfqItemId: null,
    quantity: null,
    unitPriceMinor: null,
} satisfies SubmitQuoteRevisionRequestItemsInner;

console.log(example);

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example);
console.log(exampleJSON);

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(
    exampleJSON,
) as SubmitQuoteRevisionRequestItemsInner;
console.log(exampleParsed);
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
