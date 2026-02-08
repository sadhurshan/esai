# UAT Results

Generated: 2026-01-30T08:19:47.399Z


Running 9 tests using 2 workers

  ✘  1 [chromium] › tests\e2e\uat\uat-smoke.spec.ts:17:3 › UAT smoke: buyer › buyer can reach core procurement hubs (11.9s)
  ✘  2 [chromium] › tests\e2e\uat\uat-smoke.spec.ts:66:3 › UAT smoke: supplier › supplier can reach open rfqs and invoices (12.2s)
  ✓  3 [chromium] › tests\e2e\uat\uat-smoke.spec.ts:98:3 › UAT smoke: super admin › admin can reach supplier applications and audit log (6.6s)
  ✘  5 [firefox] › tests\e2e\uat\uat-smoke.spec.ts:66:3 › UAT smoke: supplier › supplier can reach open rfqs and invoices (20.3s)
  ✘  4 [firefox] › tests\e2e\uat\uat-smoke.spec.ts:17:3 › UAT smoke: buyer › buyer can reach core procurement hubs (32.8s)
  ✘  7 [webkit] › tests\e2e\uat\uat-smoke.spec.ts:17:3 › UAT smoke: buyer › buyer can reach core procurement hubs (22.8s)
  ✓  6 [firefox] › tests\e2e\uat\uat-smoke.spec.ts:98:3 › UAT smoke: super admin › admin can reach supplier applications and audit log (22.2s)
  ✓  9 [webkit] › tests\e2e\uat\uat-smoke.spec.ts:98:3 › UAT smoke: super admin › admin can reach supplier applications and audit log (9.4s)
  ✘  8 [webkit] › tests\e2e\uat\uat-smoke.spec.ts:66:3 › UAT smoke: supplier › supplier can reach open rfqs and invoices (11.5s)


  1) [chromium] › tests\e2e\uat\uat-smoke.spec.ts:17:3 › UAT smoke: buyer › buyer can reach core procurement hubs 

    Error: [2mexpect([22m[31mlocator[39m[2m).[22mtoBeVisible[2m([22m[2m)[22m failed

    Locator: getByText('Status', { exact: true })
    Expected: visible
    Error: strict mode violation: getByText('Status', { exact: true }) resolved to 2 elements:
        1) <label class="text-xs font-medium uppercase text-muted-foreground">Status</label> aka locator('label').filter({ hasText: 'Status' })
        2) <span>Status</span> aka getByRole('table').getByText('Status')

    Call log:
    [2m  - Expect "toBeVisible" with timeout 5000ms[22m
    [2m  - waiting for getByText('Status', { exact: true })[22m


      56 |     await expect(page.getByRole('heading', { name: 'Invoices', level: 1 })).toBeVisible();
      57 |     await expect(page.getByRole('button', { name: /go to purchase orders/i })).toBeVisible();
    > 58 |     await expect(page.getByText('Status', { exact: true })).toBeVisible();
         |                                                             ^
      59 |     await expect(page.getByText('Supplier', { exact: true })).toBeVisible();
      60 |   });
      61 | });
        at C:\Users\sadhu\Herd\elements-supply-ai\tests\e2e\uat\uat-smoke.spec.ts:58:61

    attachment #1: screenshot (image/png) ──────────────────────────────────────────────────────────
    test-results\uat-uat-smoke-UAT-smoke-bu-25d56-reach-core-procurement-hubs-chromium\test-failed-1.png
    ────────────────────────────────────────────────────────────────────────────────────────────────

    attachment #2: video (video/webm) ──────────────────────────────────────────────────────────────
    test-results\uat-uat-smoke-UAT-smoke-bu-25d56-reach-core-procurement-hubs-chromium\video.webm
    ────────────────────────────────────────────────────────────────────────────────────────────────

    Error Context: test-results\uat-uat-smoke-UAT-smoke-bu-25d56-reach-core-procurement-hubs-chromium\error-context.md

  2) [chromium] › tests\e2e\uat\uat-smoke.spec.ts:66:3 › UAT smoke: supplier › supplier can reach open rfqs and invoices 

    Error: [2mexpect([22m[31mlocator[39m[2m).[22mtoBeVisible[2m([22m[2m)[22m failed

    Locator: getByRole('heading', { name: /supplier workspace/i })
    Expected: visible
    Timeout: 5000ms
    Error: element(s) not found

    Call log:
    [2m  - Expect "toBeVisible" with timeout 5000ms[22m
    [2m  - waiting for getByRole('heading', { name: /supplier workspace/i })[22m


      68 |
      69 |     await page.goto('/app');
    > 70 |     await expect(page.getByRole('heading', { name: /supplier workspace/i })).toBeVisible();
         |                                                                              ^
      71 |     await expect(page.getByRole('button', { name: /manage quotes/i })).toBeVisible();
      72 |     await expect(page.getByText(/new rfq invites/i)).toBeVisible();
      73 |
        at C:\Users\sadhu\Herd\elements-supply-ai\tests\e2e\uat\uat-smoke.spec.ts:70:78

    attachment #1: screenshot (image/png) ──────────────────────────────────────────────────────────
    test-results\uat-uat-smoke-UAT-smoke-su-efb39-each-open-rfqs-and-invoices-chromium\test-failed-1.png
    ────────────────────────────────────────────────────────────────────────────────────────────────

    attachment #2: video (video/webm) ──────────────────────────────────────────────────────────────
    test-results\uat-uat-smoke-UAT-smoke-su-efb39-each-open-rfqs-and-invoices-chromium\video.webm
    ────────────────────────────────────────────────────────────────────────────────────────────────

    Error Context: test-results\uat-uat-smoke-UAT-smoke-su-efb39-each-open-rfqs-and-invoices-chromium\error-context.md

  3) [firefox] › tests\e2e\uat\uat-smoke.spec.ts:17:3 › UAT smoke: buyer › buyer can reach core procurement hubs 

    Error: [2mexpect([22m[31mlocator[39m[2m).[22mtoBeVisible[2m([22m[2m)[22m failed

    Locator: getByText('Status', { exact: true })
    Expected: visible
    Error: strict mode violation: getByText('Status', { exact: true }) resolved to 2 elements:
        1) <label class="text-xs font-medium uppercase text-muted-foreground">Status</label> aka locator('label').filter({ hasText: 'Status' })
        2) <span>Status</span> aka getByRole('table').getByText('Status')

    Call log:
    [2m  - Expect "toBeVisible" with timeout 5000ms[22m
    [2m  - waiting for getByText('Status', { exact: true })[22m


      56 |     await expect(page.getByRole('heading', { name: 'Invoices', level: 1 })).toBeVisible();
      57 |     await expect(page.getByRole('button', { name: /go to purchase orders/i })).toBeVisible();
    > 58 |     await expect(page.getByText('Status', { exact: true })).toBeVisible();
         |                                                             ^
      59 |     await expect(page.getByText('Supplier', { exact: true })).toBeVisible();
      60 |   });
      61 | });
        at C:\Users\sadhu\Herd\elements-supply-ai\tests\e2e\uat\uat-smoke.spec.ts:58:61

    attachment #1: screenshot (image/png) ──────────────────────────────────────────────────────────
    test-results\uat-uat-smoke-UAT-smoke-bu-25d56-reach-core-procurement-hubs-firefox\test-failed-1.png
    ────────────────────────────────────────────────────────────────────────────────────────────────

    attachment #2: video (video/webm) ──────────────────────────────────────────────────────────────
    test-results\uat-uat-smoke-UAT-smoke-bu-25d56-reach-core-procurement-hubs-firefox\video.webm
    ────────────────────────────────────────────────────────────────────────────────────────────────

    Error Context: test-results\uat-uat-smoke-UAT-smoke-bu-25d56-reach-core-procurement-hubs-firefox\error-context.md

  4) [firefox] › tests\e2e\uat\uat-smoke.spec.ts:66:3 › UAT smoke: supplier › supplier can reach open rfqs and invoices 

    Error: [2mexpect([22m[31mlocator[39m[2m).[22mtoBeVisible[2m([22m[2m)[22m failed

    Locator: getByRole('heading', { name: /supplier workspace/i })
    Expected: visible
    Timeout: 5000ms
    Error: element(s) not found

    Call log:
    [2m  - Expect "toBeVisible" with timeout 5000ms[22m
    [2m  - waiting for getByRole('heading', { name: /supplier workspace/i })[22m


      68 |
      69 |     await page.goto('/app');
    > 70 |     await expect(page.getByRole('heading', { name: /supplier workspace/i })).toBeVisible();
         |                                                                              ^
      71 |     await expect(page.getByRole('button', { name: /manage quotes/i })).toBeVisible();
      72 |     await expect(page.getByText(/new rfq invites/i)).toBeVisible();
      73 |
        at C:\Users\sadhu\Herd\elements-supply-ai\tests\e2e\uat\uat-smoke.spec.ts:70:78

    attachment #1: screenshot (image/png) ──────────────────────────────────────────────────────────
    test-results\uat-uat-smoke-UAT-smoke-su-efb39-each-open-rfqs-and-invoices-firefox\test-failed-1.png
    ────────────────────────────────────────────────────────────────────────────────────────────────

    attachment #2: video (video/webm) ──────────────────────────────────────────────────────────────
    test-results\uat-uat-smoke-UAT-smoke-su-efb39-each-open-rfqs-and-invoices-firefox\video.webm
    ────────────────────────────────────────────────────────────────────────────────────────────────

    Error Context: test-results\uat-uat-smoke-UAT-smoke-su-efb39-each-open-rfqs-and-invoices-firefox\error-context.md

  5) [webkit] › tests\e2e\uat\uat-smoke.spec.ts:17:3 › UAT smoke: buyer › buyer can reach core procurement hubs 

    Error: [2mexpect([22m[31mlocator[39m[2m).[22mtoBeVisible[2m([22m[2m)[22m failed

    Locator: getByText('Status', { exact: true })
    Expected: visible
    Error: strict mode violation: getByText('Status', { exact: true }) resolved to 2 elements:
        1) <label class="text-xs font-medium uppercase text-muted-foreground">Status</label> aka locator('label').filter({ hasText: 'Status' })
        2) <span>Status</span> aka getByRole('table').getByText('Status')

    Call log:
    [2m  - Expect "toBeVisible" with timeout 5000ms[22m
    [2m  - waiting for getByText('Status', { exact: true })[22m


      56 |     await expect(page.getByRole('heading', { name: 'Invoices', level: 1 })).toBeVisible();
      57 |     await expect(page.getByRole('button', { name: /go to purchase orders/i })).toBeVisible();
    > 58 |     await expect(page.getByText('Status', { exact: true })).toBeVisible();
         |                                                             ^
      59 |     await expect(page.getByText('Supplier', { exact: true })).toBeVisible();
      60 |   });
      61 | });
        at C:\Users\sadhu\Herd\elements-supply-ai\tests\e2e\uat\uat-smoke.spec.ts:58:61

    attachment #1: screenshot (image/png) ──────────────────────────────────────────────────────────
    test-results\uat-uat-smoke-UAT-smoke-bu-25d56-reach-core-procurement-hubs-webkit\test-failed-1.png
    ────────────────────────────────────────────────────────────────────────────────────────────────

    attachment #2: video (video/webm) ──────────────────────────────────────────────────────────────
    test-results\uat-uat-smoke-UAT-smoke-bu-25d56-reach-core-procurement-hubs-webkit\video.webm
    ────────────────────────────────────────────────────────────────────────────────────────────────

    Error Context: test-results\uat-uat-smoke-UAT-smoke-bu-25d56-reach-core-procurement-hubs-webkit\error-context.md

  6) [webkit] › tests\e2e\uat\uat-smoke.spec.ts:66:3 › UAT smoke: supplier › supplier can reach open rfqs and invoices 

    Error: [2mexpect([22m[31mlocator[39m[2m).[22mtoBeVisible[2m([22m[2m)[22m failed

    Locator: getByRole('heading', { name: /supplier workspace/i })
    Expected: visible
    Timeout: 5000ms
    Error: element(s) not found

    Call log:
    [2m  - Expect "toBeVisible" with timeout 5000ms[22m
    [2m  - waiting for getByRole('heading', { name: /supplier workspace/i })[22m


      68 |
      69 |     await page.goto('/app');
    > 70 |     await expect(page.getByRole('heading', { name: /supplier workspace/i })).toBeVisible();
         |                                                                              ^
      71 |     await expect(page.getByRole('button', { name: /manage quotes/i })).toBeVisible();
      72 |     await expect(page.getByText(/new rfq invites/i)).toBeVisible();
      73 |
        at C:\Users\sadhu\Herd\elements-supply-ai\tests\e2e\uat\uat-smoke.spec.ts:70:78

    attachment #1: screenshot (image/png) ──────────────────────────────────────────────────────────
    test-results\uat-uat-smoke-UAT-smoke-su-efb39-each-open-rfqs-and-invoices-webkit\test-failed-1.png
    ────────────────────────────────────────────────────────────────────────────────────────────────

    attachment #2: video (video/webm) ──────────────────────────────────────────────────────────────
    test-results\uat-uat-smoke-UAT-smoke-su-efb39-each-open-rfqs-and-invoices-webkit\video.webm
    ────────────────────────────────────────────────────────────────────────────────────────────────

    Error Context: test-results\uat-uat-smoke-UAT-smoke-su-efb39-each-open-rfqs-and-invoices-webkit\error-context.md

  6 failed
    [chromium] › tests\e2e\uat\uat-smoke.spec.ts:17:3 › UAT smoke: buyer › buyer can reach core procurement hubs 
    [chromium] › tests\e2e\uat\uat-smoke.spec.ts:66:3 › UAT smoke: supplier › supplier can reach open rfqs and invoices 
    [firefox] › tests\e2e\uat\uat-smoke.spec.ts:17:3 › UAT smoke: buyer › buyer can reach core procurement hubs 
    [firefox] › tests\e2e\uat\uat-smoke.spec.ts:66:3 › UAT smoke: supplier › supplier can reach open rfqs and invoices 
    [webkit] › tests\e2e\uat\uat-smoke.spec.ts:17:3 › UAT smoke: buyer › buyer can reach core procurement hubs 
    [webkit] › tests\e2e\uat\uat-smoke.spec.ts:66:3 › UAT smoke: supplier › supplier can reach open rfqs and invoices 
  3 passed (1.6m)

[36m  Serving HTML report at http://localhost:9323. Press Ctrl+C to quit.[39m
