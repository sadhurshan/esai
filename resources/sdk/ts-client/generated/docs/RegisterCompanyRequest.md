
# RegisterCompanyRequest


## Properties

Name | Type
------------ | -------------
`name` | string
`country` | string
`primaryContactName` | string
`primaryContactEmail` | string
`primaryContactPhone` | string
`website` | string

## Example

```typescript
import type { RegisterCompanyRequest } from ''

// TODO: Update the object below with actual values
const example = {
  "name": null,
  "country": null,
  "primaryContactName": null,
  "primaryContactEmail": null,
  "primaryContactPhone": null,
  "website": null,
} satisfies RegisterCompanyRequest

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as RegisterCompanyRequest
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


