
# QuoteRevision


## Properties

Name | Type
------------ | -------------
`id` | string
`quoteId` | string
`revisionNo` | number
`status` | string
`submittedAt` | Date
`note` | string

## Example

```typescript
import type { QuoteRevision } from ''

// TODO: Update the object below with actual values
const example = {
  "id": null,
  "quoteId": null,
  "revisionNo": null,
  "status": null,
  "submittedAt": null,
  "note": null,
} satisfies QuoteRevision

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as QuoteRevision
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


