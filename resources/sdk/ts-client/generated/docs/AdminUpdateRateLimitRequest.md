
# AdminUpdateRateLimitRequest


## Properties

Name | Type
------------ | -------------
`windowSeconds` | number
`maxRequests` | number
`active` | boolean

## Example

```typescript
import type { AdminUpdateRateLimitRequest } from ''

// TODO: Update the object below with actual values
const example = {
  "windowSeconds": null,
  "maxRequests": null,
  "active": null,
} satisfies AdminUpdateRateLimitRequest

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as AdminUpdateRateLimitRequest
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


