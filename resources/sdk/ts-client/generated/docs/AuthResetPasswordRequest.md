
# AuthResetPasswordRequest


## Properties

Name | Type
------------ | -------------
`token` | string
`email` | string
`password` | string
`passwordConfirmation` | string

## Example

```typescript
import type { AuthResetPasswordRequest } from ''

// TODO: Update the object below with actual values
const example = {
  "token": null,
  "email": null,
  "password": null,
  "passwordConfirmation": null,
} satisfies AuthResetPasswordRequest

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as AuthResetPasswordRequest
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


