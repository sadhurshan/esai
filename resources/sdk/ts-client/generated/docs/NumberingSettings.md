# NumberingSettings

## Properties

| Name      | Type                              |
| --------- | --------------------------------- |
| `rfq`     | [NumberingRule](NumberingRule.md) |
| `quote`   | [NumberingRule](NumberingRule.md) |
| `po`      | [NumberingRule](NumberingRule.md) |
| `invoice` | [NumberingRule](NumberingRule.md) |
| `grn`     | [NumberingRule](NumberingRule.md) |
| `credit`  | [NumberingRule](NumberingRule.md) |

## Example

```typescript
import type { NumberingSettings } from '';

// TODO: Update the object below with actual values
const example = {
    rfq: null,
    quote: null,
    po: null,
    invoice: null,
    grn: null,
    credit: null,
} satisfies NumberingSettings;

console.log(example);

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example);
console.log(exampleJSON);

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as NumberingSettings;
console.log(exampleParsed);
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
