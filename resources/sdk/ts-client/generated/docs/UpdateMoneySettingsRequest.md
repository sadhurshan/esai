
# UpdateMoneySettingsRequest


## Properties

Name | Type
------------ | -------------
`baseCurrency` | string
`pricingCurrency` | string
`fxSource` | string
`priceRoundRule` | string
`taxRegime` | string
`defaults` | { [key: string]: any; }

## Example

```typescript
import type { UpdateMoneySettingsRequest } from ''

// TODO: Update the object below with actual values
const example = {
  "baseCurrency": null,
  "pricingCurrency": null,
  "fxSource": null,
  "priceRoundRule": null,
  "taxRegime": null,
  "defaults": null,
} satisfies UpdateMoneySettingsRequest

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as UpdateMoneySettingsRequest
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


