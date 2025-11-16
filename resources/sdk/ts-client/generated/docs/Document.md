
# Document


## Properties

Name | Type
------------ | -------------
`id` | number
`companyId` | number
`documentableType` | string
`documentableId` | number
`kind` | string
`category` | string
`visibility` | string
`version` | number
`filename` | string
`mime` | string
`sizeBytes` | number
`hash` | string
`expiresAt` | Date
`meta` | object
`watermark` | object
`createdAt` | Date
`updatedAt` | Date
`isExpired` | boolean
`isPublic` | boolean
`downloadUrl` | string

## Example

```typescript
import type { Document } from ''

// TODO: Update the object below with actual values
const example = {
  "id": null,
  "companyId": null,
  "documentableType": null,
  "documentableId": null,
  "kind": null,
  "category": null,
  "visibility": null,
  "version": null,
  "filename": null,
  "mime": null,
  "sizeBytes": null,
  "hash": null,
  "expiresAt": null,
  "meta": null,
  "watermark": null,
  "createdAt": null,
  "updatedAt": null,
  "isExpired": null,
  "isPublic": null,
  "downloadUrl": null,
} satisfies Document

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as Document
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


