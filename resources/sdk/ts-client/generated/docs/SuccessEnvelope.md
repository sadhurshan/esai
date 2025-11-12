
# SuccessEnvelope

Wrapper for all successful responses. `meta.request_id` always echoes the inbound `X-Request-Id` or an internally generated UUID.

## Properties

Name | Type
------------ | -------------
`status` | string
`message` | string
`data` | any
`meta` | [RequestMeta](RequestMeta.md)

## Example

```typescript
import type { SuccessEnvelope } from ''

// TODO: Update the object below with actual values
const example = {
  "status": null,
  "message": null,
  "data": null,
  "meta": null,
} satisfies SuccessEnvelope

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as SuccessEnvelope
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


