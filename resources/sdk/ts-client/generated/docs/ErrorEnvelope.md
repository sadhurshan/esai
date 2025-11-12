
# ErrorEnvelope


## Properties

Name | Type
------------ | -------------
`status` | string
`message` | string
`code` | string
`errors` | { [key: string]: Array&lt;string&gt;; }
`data` | any

## Example

```typescript
import type { ErrorEnvelope } from ''

// TODO: Update the object below with actual values
const example = {
  "status": null,
  "message": null,
  "code": null,
  "errors": null,
  "data": null,
} satisfies ErrorEnvelope

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as ErrorEnvelope
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


