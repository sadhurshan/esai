
# CompanySettings


## Properties

Name | Type
------------ | -------------
`legalName` | string
`displayName` | string
`taxId` | string
`registrationNumber` | string
`emails` | Array&lt;string&gt;
`phones` | Array&lt;string&gt;
`billTo` | [CompanyAddress](CompanyAddress.md)
`shipFrom` | [CompanyAddress](CompanyAddress.md)
`logoUrl` | string
`markUrl` | string

## Example

```typescript
import type { CompanySettings } from ''

// TODO: Update the object below with actual values
const example = {
  "legalName": null,
  "displayName": null,
  "taxId": null,
  "registrationNumber": null,
  "emails": null,
  "phones": null,
  "billTo": null,
  "shipFrom": null,
  "logoUrl": null,
  "markUrl": null,
} satisfies CompanySettings

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as CompanySettings
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


