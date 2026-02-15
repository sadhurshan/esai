# ListSupplierQuotes200ResponseAllOfData

## Properties

| Name    | Type                                                                                        |
| ------- | ------------------------------------------------------------------------------------------- |
| `items` | [Array&lt;Quote&gt;](Quote.md)                                                              |
| `meta`  | [ListSupplierQuotes200ResponseAllOfDataMeta](ListSupplierQuotes200ResponseAllOfDataMeta.md) |

## Example

```typescript
import type { ListSupplierQuotes200ResponseAllOfData } from '';

// TODO: Update the object below with actual values
const example = {
    items: null,
    meta: null,
} satisfies ListSupplierQuotes200ResponseAllOfData;

console.log(example);

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example);
console.log(exampleJSON);

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(
    exampleJSON,
) as ListSupplierQuotes200ResponseAllOfData;
console.log(exampleParsed);
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
