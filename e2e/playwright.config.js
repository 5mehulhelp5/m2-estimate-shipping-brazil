// End-to-end tests against a running Magento store. See README.md in this folder.
import { defineConfig } from '@playwright/test';

const browser = process.env.QA_BROWSER; // path to a Chromium-based browser; unset = Playwright's own Chromium

export default defineConfig({
  testDir: '.',
  testMatch: '*.spec.js',
  timeout: 120_000,
  expect: { timeout: 15_000 },
  workers: 1,
  retries: 0,
  reporter: [['list'], ['json', { outputFile: `test-results/report-${process.env.QA_PHASE ?? 'after'}.json` }]],
  outputDir: 'test-results/artifacts',
  use: {
    baseURL: process.env.QA_BASE_URL ?? 'http://localhost',
    headless: true,
    locale: 'en-US', // the store is pt_BR: proves prices follow the store format, not the browser's
    viewport: { width: 1280, height: 1300 },
    launchOptions: browser ? { executablePath: browser } : {},
  },
});
