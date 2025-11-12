
# Rfq


## Properties

Name | Type
------------ | -------------
`id` | string
`number` | string
`itemName` | string
`type` | string
`quantity` | number
`material` | string
`method` | string
`tolerance` | string
`finish` | string
`clientCompany` | string
`status` | string
`deadlineAt` | Date
`sentAt` | Date
`isOpenBidding` | boolean
`notes` | string
`cadPath` | string
`createdAt` | Date
`updatedAt` | Date
`items` | [Array&lt;RfqItem&gt;](RfqItem.md)
`quotes` | [Array&lt;QuoteSummary&gt;](QuoteSummary.md)

## Example

```typescript
import type { Rfq } from ''

// TODO: Update the object below with actual values
const example = {
  "id": null,
  "number": null,
  "itemName": null,
  "type": null,
  "quantity": null,
  "material": null,
  "method": null,
  "tolerance": null,
  "finish": null,
  "clientCompany": null,
  "status": null,
  "deadlineAt": null,
  "sentAt": null,
  "isOpenBidding": null,
  "notes": null,
  "cadPath": null,
  "createdAt": null,
  "updatedAt": null,
  "items": null,
  "quotes": null,
} satisfies Rfq

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as Rfq
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


