
# SendPurchaseOrder200ResponseAllOfData


## Properties

Name | Type
------------ | -------------
`purchaseOrder` | [PurchaseOrder](PurchaseOrder.md)
`delivery` | [PurchaseOrderDelivery](PurchaseOrderDelivery.md)

## Example

```typescript
import type { SendPurchaseOrder200ResponseAllOfData } from ''

// TODO: Update the object below with actual values
const example = {
  "purchaseOrder": null,
  "delivery": null,
} satisfies SendPurchaseOrder200ResponseAllOfData

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as SendPurchaseOrder200ResponseAllOfData
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


