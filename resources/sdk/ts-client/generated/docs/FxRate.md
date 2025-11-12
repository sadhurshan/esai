
# FxRate


## Properties

Name | Type
------------ | -------------
`id` | number
`baseCode` | string
`quoteCode` | string
`rate` | string
`asOf` | Date
`createdAt` | Date
`updatedAt` | Date

## Example

```typescript
import type { FxRate } from ''

// TODO: Update the object below with actual values
const example = {
  "id": null,
  "baseCode": null,
  "quoteCode": null,
  "rate": null,
  "asOf": null,
  "createdAt": null,
  "updatedAt": null,
} satisfies FxRate

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as FxRate
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


