
# SupplierApplicationPayload


## Properties

Name | Type
------------ | -------------
`capabilities` | [SupplierApplicationPayloadCapabilities](SupplierApplicationPayloadCapabilities.md)
`description` | string
`address` | string
`country` | string
`city` | string
`moq` | number
`minOrderQty` | number
`leadTimeDays` | number
`geo` | [SupplierApplicationPayloadGeo](SupplierApplicationPayloadGeo.md)
`certifications` | Array&lt;string&gt;
`facilities` | string
`website` | string
`contact` | [SupplierApplicationPayloadContact](SupplierApplicationPayloadContact.md)
`notes` | string
`documents` | Array&lt;number&gt;

## Example

```typescript
import type { SupplierApplicationPayload } from ''

// TODO: Update the object below with actual values
const example = {
  "capabilities": null,
  "description": null,
  "address": null,
  "country": null,
  "city": null,
  "moq": null,
  "minOrderQty": null,
  "leadTimeDays": null,
  "geo": null,
  "certifications": null,
  "facilities": null,
  "website": null,
  "contact": null,
  "notes": null,
  "documents": null,
} satisfies SupplierApplicationPayload

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as SupplierApplicationPayload
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


