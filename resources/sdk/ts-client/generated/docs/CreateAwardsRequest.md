
# CreateAwardsRequest


## Properties

Name | Type
------------ | -------------
`rfqId` | number
`items` | [Array&lt;CreateAwardsRequestItemsInner&gt;](CreateAwardsRequestItemsInner.md)

## Example

```typescript
import type { CreateAwardsRequest } from ''

// TODO: Update the object below with actual values
const example = {
  "rfqId": null,
  "items": null,
} satisfies CreateAwardsRequest

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as CreateAwardsRequest
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


