
# TaxCode


## Properties

Name | Type
------------ | -------------
`id` | number
`companyId` | number
`code` | string
`name` | string
`type` | string
`ratePercent` | number
`isCompound` | boolean
`active` | boolean
`meta` | object
`createdAt` | Date
`updatedAt` | Date

## Example

```typescript
import type { TaxCode } from ''

// TODO: Update the object below with actual values
const example = {
  "id": null,
  "companyId": null,
  "code": null,
  "name": null,
  "type": null,
  "ratePercent": null,
  "isCompound": null,
  "active": null,
  "meta": null,
  "createdAt": null,
  "updatedAt": null,
} satisfies TaxCode

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as TaxCode
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


