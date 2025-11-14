
# RfqAttachment

Document attached to an RFQ per the documents deep spec.

## Properties

Name | Type
------------ | -------------
`id` | string
`documentId` | string
`filename` | string
`mime` | string
`sizeBytes` | number
`url` | string
`uploadedAt` | Date
`uploadedBy` | [RfqAttachmentUploadedBy](RfqAttachmentUploadedBy.md)

## Example

```typescript
import type { RfqAttachment } from ''

// TODO: Update the object below with actual values
const example = {
  "id": null,
  "documentId": null,
  "filename": null,
  "mime": null,
  "sizeBytes": null,
  "url": null,
  "uploadedAt": null,
  "uploadedBy": null,
} satisfies RfqAttachment

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as RfqAttachment
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


