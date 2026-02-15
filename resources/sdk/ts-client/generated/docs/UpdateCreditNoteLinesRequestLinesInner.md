# UpdateCreditNoteLinesRequestLinesInner

## Properties

| Name            | Type   |
| --------------- | ------ |
| `invoiceLineId` | number |
| `qtyToCredit`   | number |
| `description`   | string |
| `uom`           | string |

## Example

```typescript
import type { UpdateCreditNoteLinesRequestLinesInner } from '';

// TODO: Update the object below with actual values
const example = {
    invoiceLineId: null,
    qtyToCredit: null,
    description: null,
    uom: null,
} satisfies UpdateCreditNoteLinesRequestLinesInner;

console.log(example);

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example);
console.log(exampleJSON);

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(
    exampleJSON,
) as UpdateCreditNoteLinesRequestLinesInner;
console.log(exampleParsed);
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
