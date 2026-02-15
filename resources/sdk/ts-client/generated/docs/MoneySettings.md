# MoneySettings

## Properties

| Name              | Type                                                      |
| ----------------- | --------------------------------------------------------- |
| `id`              | number                                                    |
| `companyId`       | number                                                    |
| `baseCurrency`    | [MoneySettingsBaseCurrency](MoneySettingsBaseCurrency.md) |
| `pricingCurrency` | [MoneySettingsBaseCurrency](MoneySettingsBaseCurrency.md) |
| `fxSource`        | string                                                    |
| `priceRoundRule`  | string                                                    |
| `taxRegime`       | string                                                    |
| `defaults`        | object                                                    |
| `createdAt`       | Date                                                      |
| `updatedAt`       | Date                                                      |

## Example

```typescript
import type { MoneySettings } from '';

// TODO: Update the object below with actual values
const example = {
    id: null,
    companyId: null,
    baseCurrency: null,
    pricingCurrency: null,
    fxSource: null,
    priceRoundRule: null,
    taxRegime: null,
    defaults: null,
    createdAt: null,
    updatedAt: null,
} satisfies MoneySettings;

console.log(example);

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example);
console.log(exampleJSON);

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as MoneySettings;
console.log(exampleParsed);
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
