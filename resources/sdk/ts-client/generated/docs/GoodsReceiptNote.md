
# GoodsReceiptNote


## Properties

Name | Type
------------ | -------------
`id` | number
`companyId` | number
`purchaseOrderId` | number
`number` | string
`status` | string
`inspectedById` | number
`inspectedAt` | Date
`inspector` | [GoodsReceiptNoteInspector](GoodsReceiptNoteInspector.md)
`lines` | [Array&lt;GoodsReceiptLine&gt;](GoodsReceiptLine.md)
`createdAt` | Date
`updatedAt` | Date

## Example

```typescript
import type { GoodsReceiptNote } from ''

// TODO: Update the object below with actual values
const example = {
  "id": null,
  "companyId": null,
  "purchaseOrderId": null,
  "number": null,
  "status": null,
  "inspectedById": null,
  "inspectedAt": null,
  "inspector": null,
  "lines": null,
  "createdAt": null,
  "updatedAt": null,
} satisfies GoodsReceiptNote

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as GoodsReceiptNote
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


