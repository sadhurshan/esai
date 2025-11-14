
# PublishRfqRequest


## Properties

Name | Type
------------ | -------------
`dueAt` | Date
`publishAt` | Date
`notifySuppliers` | boolean
`message` | string

## Example

```typescript
import type { PublishRfqRequest } from ''

// TODO: Update the object below with actual values
const example = {
  "dueAt": null,
  "publishAt": null,
  "notifySuppliers": null,
  "message": null,
} satisfies PublishRfqRequest

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as PublishRfqRequest
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


