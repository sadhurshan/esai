
# UpdateCompanyRequest


## Properties

Name | Type
------------ | -------------
`name` | string
`registrationNo` | string
`taxId` | string
`country` | string
`emailDomain` | string
`primaryContactName` | string
`primaryContactEmail` | string
`primaryContactPhone` | string
`address` | string
`phone` | string
`website` | string
`region` | string

## Example

```typescript
import type { UpdateCompanyRequest } from ''

// TODO: Update the object below with actual values
const example = {
  "name": null,
  "registrationNo": null,
  "taxId": null,
  "country": null,
  "emailDomain": null,
  "primaryContactName": null,
  "primaryContactEmail": null,
  "primaryContactPhone": null,
  "address": null,
  "phone": null,
  "website": null,
  "region": null,
} satisfies UpdateCompanyRequest

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as UpdateCompanyRequest
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


