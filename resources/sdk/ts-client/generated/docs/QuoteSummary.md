
# QuoteSummary


## Properties

Name | Type
------------ | -------------
`id` | string
`rfqId` | string
`supplierId` | string
`status` | string
`total` | string
`totalMinor` | number
`currency` | string
`submittedAt` | Date

## Example

```typescript
import type { QuoteSummary } from ''

// TODO: Update the object below with actual values
const example = {
  "id": null,
  "rfqId": null,
  "supplierId": null,
  "status": null,
  "total": null,
  "totalMinor": null,
  "currency": null,
  "submittedAt": null,
} satisfies QuoteSummary

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as QuoteSummary
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


