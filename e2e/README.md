# End-to-end tests

Playwright tests that run against a real Magento store with the module installed and a Brazilian setup (BRL, Flat Rate
and Table Rate enabled). They are not part of the CI workflow: they need a store.

```bash
npm i -D @playwright/test           # browsers: `npx playwright install chromium`, or point QA_BROWSER at a
                                    # Chromium-based browser you already have
QA_BASE_URL=http://localhost npx playwright test --config e2e/playwright.config.js
```

Environment variables: `QA_BASE_URL` (default `http://localhost`), `QA_BROWSER` (browser executable) and
`QA_PHASE` (`before` / `after`, only names the screenshot folder).

The expected prices come from the carriers the store was seeded with:

| Carrier (title) | Method | Price |
|---|---|---|
| Flat Rate | Fixed | R$ 15,00 |
| Best Way (Table Rate, by package weight) | Table Rate | R$ 12,50 for postcode 88010400; otherwise R$ 20,00 below 5 kg and R$ 35,00 from 5 kg |

Products: a simple product, a configurable product (1 kg per item, sizes 32/33/34/36) and a dynamic-price bundle, all
from the Magento sample data.
