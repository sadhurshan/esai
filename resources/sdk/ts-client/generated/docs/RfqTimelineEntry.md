
# RfqTimelineEntry


## Properties

Name | Type
------------ | -------------
`createdAt` | Date
`updatedAt` | Date
`deletedAt` | Date
`event` | string
`actor` | [RfqTimelineEntryAllOfActor](RfqTimelineEntryAllOfActor.md)
`context` | object

## Example

```typescript
import type { RfqTimelineEntry } from ''

// TODO: Update the object below with actual values
const example = {
  "createdAt": null,
  "updatedAt": null,
  "deletedAt": null,
  "event": null,
  "actor": null,
  "context": null,
} satisfies RfqTimelineEntry

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as RfqTimelineEntry
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


