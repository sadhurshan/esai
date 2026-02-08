# UAT Automation

This automates a staging UAT smoke pass using Playwright and the stable staging accounts.

## 1) Generate the UAT environment file
This pulls credentials from docs/Stable Staging Data.md and writes .env.uat at the repo root.

```
node tools/uat/generate-uat-env.mjs
```

## 2) Run UAT smoke tests
This runs Playwright against tests/e2e/uat using the .env.uat environment file.

```
node tools/uat/run-uat.mjs
```

## 3) Expand coverage
Add more UAT flows under tests/e2e/uat and map stories from docs/UAT_BACKLOG_CHECKLIST.md.

## Notes
- .env.uat is ignored by git.
- Set PLAYWRIGHT_BASE_URL if you want to override the staging URL.
