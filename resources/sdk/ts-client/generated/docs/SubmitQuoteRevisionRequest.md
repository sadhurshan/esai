
# SubmitQuoteRevisionRequest


## Properties

Name | Type
------------ | -------------
`note` | string
`items` | [Array&lt;SubmitQuoteRequestItemsInner&gt;](SubmitQuoteRequestItemsInner.md)

## Example

```typescript
import type { SubmitQuoteRevisionRequest } from ''

// TODO: Update the object below with actual values
const example = {
  "note": null,
  "items": null,
} satisfies SubmitQuoteRevisionRequest

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as SubmitQuoteRevisionRequest
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


