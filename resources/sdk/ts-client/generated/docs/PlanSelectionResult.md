# PlanSelectionResult

## Properties

| Name      | Type                                                        |
| --------- | ----------------------------------------------------------- |
| `company` | [PlanSelectionResultCompany](PlanSelectionResultCompany.md) |
| `plan`    | [PlanCatalogEntry](PlanCatalogEntry.md)                     |

## Example

```typescript
import type { PlanSelectionResult } from '';

// TODO: Update the object below with actual values
const example = {
    company: null,
    plan: null,
} satisfies PlanSelectionResult;

console.log(example);

// Convert the instance to a JSON string
const exampleJSON: string = JSON.stringify(example);
console.log(exampleJSON);

// Parse the JSON string back to an object
const exampleParsed = JSON.parse(exampleJSON) as PlanSelectionResult;
console.log(exampleParsed);
```

[[Back to top]](#) [[Back to API list]](../README.md#api-endpoints) [[Back to Model list]](../README.md#models) [[Back to README]](../README.md)
