# StoreCompanyGrnRequest

## Properties

| Name              | Type                                                                         |
| ----------------- | ---------------------------------------------------------------------------- |
| `purchaseOrderId` | number                                                                       |
| `receivedAt`      | Date                                                                         |
| `reference`       | string                                                                       |
| `notes`           | string                                                                       |
| `status`          | string                                                                       |
| `lines`           | [Array&lt;CompanyGoodsReceiptLineInput&gt;](CompanyGoodsReceiptLineInput.md) |

## Example

```typescript
import type { StoreCompanyGrnRequest } from '';

// TODO: Update the object below with actual values
const example = {
    purchaseOrderId: null,
    receivedAt: null,
    reference: null,
    notes: null,
    status: null,
    lines: null,
} satisfies StoreCompanyGrnRequest;

console.log(example);

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example);
console.log(exampleJSON);

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as StoreCompanyGrnRequest;
console.log(exampleParsed);
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
