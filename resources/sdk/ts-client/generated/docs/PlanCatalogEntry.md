
# PlanCatalogEntry


## Properties

Name | Type
------------ | -------------
`code` | string
`name` | string
`priceUsd` | number
`rfqsPerMonth` | number
`invoicesPerMonth` | number
`usersMax` | number
`storageGb` | number
`erpIntegrationsMax` | number
`analyticsEnabled` | boolean
`analyticsHistoryMonths` | number
`riskScoresEnabled` | boolean
`riskHistoryMonths` | number
`approvalsEnabled` | boolean
`approvalLevelsLimit` | number
`rmaEnabled` | boolean
`rmaMonthlyLimit` | number
`creditNotesEnabled` | boolean
`globalSearchEnabled` | boolean
`quoteRevisionsEnabled` | boolean
`digitalTwinEnabled` | boolean
`maintenanceEnabled` | boolean
`inventoryEnabled` | boolean
`inventoryHistoryMonths` | number
`prEnabled` | boolean
`multiCurrencyEnabled` | boolean
`taxEngineEnabled` | boolean
`localizationEnabled` | boolean
`exportsEnabled` | boolean
`exportRowLimit` | number
`dataExportEnabled` | boolean
`exportHistoryDays` | number
`isFree` | boolean

## Example

```typescript
import type { PlanCatalogEntry } from ''

// TODO: Update the object below with actual values
const example = {
  "code": null,
  "name": null,
  "priceUsd": null,
  "rfqsPerMonth": null,
  "invoicesPerMonth": null,
  "usersMax": null,
  "storageGb": null,
  "erpIntegrationsMax": null,
  "analyticsEnabled": null,
  "analyticsHistoryMonths": null,
  "riskScoresEnabled": null,
  "riskHistoryMonths": null,
  "approvalsEnabled": null,
  "approvalLevelsLimit": null,
  "rmaEnabled": null,
  "rmaMonthlyLimit": null,
  "creditNotesEnabled": null,
  "globalSearchEnabled": null,
  "quoteRevisionsEnabled": null,
  "digitalTwinEnabled": null,
  "maintenanceEnabled": null,
  "inventoryEnabled": null,
  "inventoryHistoryMonths": null,
  "prEnabled": null,
  "multiCurrencyEnabled": null,
  "taxEngineEnabled": null,
  "localizationEnabled": null,
  "exportsEnabled": null,
  "exportRowLimit": null,
  "dataExportEnabled": null,
  "exportHistoryDays": null,
  "isFree": null,
} satisfies PlanCatalogEntry

console.log(example)

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example)
console.log(exampleJSON)

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as PlanCatalogEntry
console.log(exampleParsed)
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)


