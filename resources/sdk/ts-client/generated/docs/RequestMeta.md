
# RequestMeta

Metadata applied to every successful response. Includes request correlation identifiers and pagination cursors.

## Properties

Name | Type
------------ | -------------
`requestId` | string
`pagination` | [PageMeta](PageMeta.md)
`cursor` | [CursorMeta](CursorMeta.md)

## Example

```typescript
import type { RequestMeta } from ''

// TODO: Update the object below with actual values
const example = {
  "requestId": null,
  "pagination": null,
  "cursor": null,
} satisfies RequestMeta

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as RequestMeta
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


