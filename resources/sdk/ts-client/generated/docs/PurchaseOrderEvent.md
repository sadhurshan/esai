
# PurchaseOrderEvent


## Properties

Name | Type
------------ | -------------
`id` | number
`purchaseOrderId` | number
`type` | string
`summary` | string
`description` | string
`metadata` | object
`actor` | [PurchaseOrderEventActor](PurchaseOrderEventActor.md)
`occurredAt` | Date
`createdAt` | Date

## Example

```typescript
import type { PurchaseOrderEvent } from ''

// TODO: Update the object below with actual values
const example = {
  "id": null,
  "purchaseOrderId": null,
  "type": null,
  "summary": null,
  "description": null,
  "metadata": null,
  "actor": null,
  "occurredAt": null,
  "createdAt": null,
} satisfies PurchaseOrderEvent

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as PurchaseOrderEvent
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


