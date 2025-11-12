
# AdminAssignPlanRequest


## Properties

Name | Type
------------ | -------------
`planId` | number
`trialEndsAt` | Date
`seatsPurchased` | number

## Example

```typescript
import type { AdminAssignPlanRequest } from ''

// TODO: Update the object below with actual values
const example = {
  "planId": null,
  "trialEndsAt": null,
  "seatsPurchased": null,
} satisfies AdminAssignPlanRequest

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as AdminAssignPlanRequest
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


