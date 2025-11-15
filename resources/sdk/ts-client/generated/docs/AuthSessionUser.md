
# AuthSessionUser


## Properties

Name | Type
------------ | -------------
`id` | number
`name` | string
`email` | string
`role` | string
`companyId` | number

## Example

```typescript
import type { AuthSessionUser } from ''

// TODO: Update the object below with actual values
const example = {
  "id": null,
  "name": null,
  "email": null,
  "role": null,
  "companyId": null,
} satisfies AuthSessionUser

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as AuthSessionUser
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


