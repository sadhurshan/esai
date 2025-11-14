
# DashboardMetrics

Aggregated counts shown on the operations dashboard.

## Properties

Name | Type
------------ | -------------
`openRfqCount` | number
`quotesAwaitingReviewCount` | number
`posAwaitingAcknowledgementCount` | number
`unpaidInvoiceCount` | number
`lowStockPartCount` | number

## Example

```typescript
import type { DashboardMetrics } from ''

// TODO: Update the object below with actual values
const example = {
  "openRfqCount": null,
  "quotesAwaitingReviewCount": null,
  "posAwaitingAcknowledgementCount": null,
  "unpaidInvoiceCount": null,
  "lowStockPartCount": null,
} satisfies DashboardMetrics

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as DashboardMetrics
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


