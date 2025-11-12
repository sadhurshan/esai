
# CompanyProfile


## Properties

Name | Type
------------ | -------------
`id` | number
`name` | string
`slug` | string
`status` | string
`supplierStatus` | string
`emailDomain` | string
`primaryContactName` | string
`primaryContactEmail` | string
`country` | string
`website` | string
`hasCompletedOnboarding` | boolean
`createdAt` | Date
`updatedAt` | Date

## Example

```typescript
import type { CompanyProfile } from ''

// TODO: Update the object below with actual values
const example = {
  "id": null,
  "name": null,
  "slug": null,
  "status": null,
  "supplierStatus": null,
  "emailDomain": null,
  "primaryContactName": null,
  "primaryContactEmail": null,
  "country": null,
  "website": null,
  "hasCompletedOnboarding": null,
  "createdAt": null,
  "updatedAt": null,
} satisfies CompanyProfile

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as CompanyProfile
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


