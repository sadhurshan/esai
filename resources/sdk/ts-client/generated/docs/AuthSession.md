
# AuthSession


## Properties

Name | Type
------------ | -------------
`token` | string
`user` | [AuthSessionUser](AuthSessionUser.md)
`company` | [AuthSessionCompany](AuthSessionCompany.md)
`featureFlags` | { [key: string]: boolean; }
`plan` | string

## Example

```typescript
import type { AuthSession } from ''

// TODO: Update the object below with actual values
const example = {
  "token": null,
  "user": null,
  "company": null,
  "featureFlags": null,
  "plan": null,
} satisfies AuthSession

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as AuthSession
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


