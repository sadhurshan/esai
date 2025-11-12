
# UpsertFxRatesRequestRowsInner


## Properties

Name | Type
------------ | -------------
`baseCode` | string
`quoteCode` | string
`rate` | number
`asOf` | Date

## Example

```typescript
import type { UpsertFxRatesRequestRowsInner } from ''

// TODO: Update the object below with actual values
const example = {
  "baseCode": null,
  "quoteCode": null,
  "rate": null,
  "asOf": null,
} satisfies UpsertFxRatesRequestRowsInner

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as UpsertFxRatesRequestRowsInner
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


