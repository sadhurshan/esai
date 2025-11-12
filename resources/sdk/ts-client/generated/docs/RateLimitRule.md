
# RateLimitRule


## Properties

Name | Type
------------ | -------------
`id` | number
`companyId` | number
`scope` | string
`windowSeconds` | number
`maxRequests` | number
`active` | boolean
`createdAt` | Date
`updatedAt` | Date

## Example

```typescript
import type { RateLimitRule } from ''

// TODO: Update the object below with actual values
const example = {
  "id": null,
  "companyId": null,
  "scope": null,
  "windowSeconds": null,
  "maxRequests": null,
  "active": null,
  "createdAt": null,
  "updatedAt": null,
} satisfies RateLimitRule

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as RateLimitRule
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


