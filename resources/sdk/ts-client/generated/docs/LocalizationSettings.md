# LocalizationSettings

## Properties

| Name           | Type                                          |
| -------------- | --------------------------------------------- |
| `timezone`     | string                                        |
| `locale`       | string                                        |
| `dateFormat`   | string                                        |
| `numberFormat` | string                                        |
| `currency`     | [CurrencyPreferences](CurrencyPreferences.md) |
| `uom`          | [UomMappings](UomMappings.md)                 |

## Example

```typescript
import type { LocalizationSettings } from '';

// TODO: Update the object below with actual values
const example = {
    timezone: null,
    locale: null,
    dateFormat: null,
    numberFormat: null,
    currency: null,
    uom: null,
} satisfies LocalizationSettings;

console.log(example);

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example);
console.log(exampleJSON);

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as LocalizationSettings;
console.log(exampleParsed);
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
