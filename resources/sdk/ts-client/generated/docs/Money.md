# Money

Monetary amount expressed in minor units. `amount` is derived from `amount_minor` and `currency` using the company rounding rules.

## Properties

| Name          | Type   |
| ------------- | ------ |
| `amountMinor` | number |
| `currency`    | string |
| `amount`      | number |

## Example

```typescript
import type { Money } from '';

// TODO: Update the object below with actual values
const example = {
    amountMinor: null,
    currency: null,
    amount: null,
} satisfies Money;

console.log(example);

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example);
console.log(exampleJSON);

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as Money;
console.log(exampleParsed);
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
