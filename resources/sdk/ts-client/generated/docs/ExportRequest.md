
# ExportRequest


## Properties

Name | Type
------------ | -------------
`id` | string
`type` | string
`status` | string
`filters` | object
`requestedBy` | [ExportRequestRequestedBy](ExportRequestRequestedBy.md)
`createdAt` | Date
`completedAt` | Date
`expiresAt` | Date
`downloadUrl` | string
`errorMessage` | string

## Example

```typescript
import type { ExportRequest } from ''

// TODO: Update the object below with actual values
const example = {
  "id": null,
  "type": null,
  "status": null,
  "filters": null,
  "requestedBy": null,
  "createdAt": null,
  "completedAt": null,
  "expiresAt": null,
  "downloadUrl": null,
  "errorMessage": null,
} satisfies ExportRequest

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as ExportRequest
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


