import { expect, test } from '@playwright/test';

import {
  chooseBundleOptions, chooseConfigurable, collectPageErrors, estimate, openProduct, parseBRL, postcodeInput, readResults, results, shot,
} from './helpers.js';

// Store seeded with: Flat Rate "Fixed" R$ 15,00 and Table Rate "Best Way" by package weight
// (>= 0 kg R$ 20,00; >= 5 kg R$ 35,00; postcode 88010400 R$ 12,50), see magento-qa/seed-qa-data.
const FLORIANOPOLIS = '88010-400';
const SAO_PAULO = '01310-100';

const price = (entry) => parseBRL(entry.price);
const widget = (page) => page.locator('.simulate-container');

test.describe('simple product', () => {
  const URL = '/joust-duffle-bag.html';

  test('lists every carrier, not only the last one, with the right prices', async ({ page }) => {
    const errors = collectPageErrors(page);
    await openProduct(page, URL);
    await shot(page, 'shipping-01-product-page-form', widget(page));

    await estimate(page, FLORIANOPOLIS);
    const carriers = await readResults(page);

    expect(Object.keys(carriers).sort()).toEqual(['Best Way', 'Flat Rate']);
    expect(price(carriers['Flat Rate'][0])).toBe(15);
    expect(price(carriers['Best Way'][0])).toBe(12.5); // postcode-specific table row
    await shot(page, 'shipping-02-simple-all-carriers', widget(page));
    expect(errors).toEqual([]);
  });

  test('a different postcode uses the general table rate row', async ({ page }) => {
    await openProduct(page, URL);
    await estimate(page, SAO_PAULO);

    const carriers = await readResults(page);
    expect(price(carriers['Best Way'][0])).toBe(20);
    expect(price(carriers['Flat Rate'][0])).toBe(15);
  });

  test('masks the postcode as 00000-000 while typing', async ({ page }) => {
    await openProduct(page, URL);
    await postcodeInput(page).pressSequentially('88010400');
    await expect(postcodeInput(page)).toHaveValue('88010-400');
  });

  test('pressing Enter estimates shipping and does not add the product to the cart', async ({ page }) => {
    await openProduct(page, URL);
    await postcodeInput(page).pressSequentially('88010400');
    await postcodeInput(page).press('Enter');

    await readResults(page);
    expect(page.url()).toContain('joust-duffle-bag.html'); // no redirect to the cart / checkout
    await expect(page.locator('.minicart-wrapper .counter.qty.empty, .minicart-wrapper .counter-number:text-is("0")').first()).toBeAttached();
  });

  test('an incomplete postcode shows a readable validation message', async ({ page }) => {
    await openProduct(page, URL);
    await estimate(page, '123');

    await expect(results(page)).toContainText(/valid postcode|CEP válido/i);
    await shot(page, 'shipping-03-invalid-postcode', widget(page));
  });
});

test.describe('configurable product', () => {
  const URL = '/apollo-running-short.html';

  test('without a chosen option the customer is told why, instead of getting garbage', async ({ page }) => {
    await openProduct(page, URL);
    await estimate(page, FLORIANOPOLIS);

    await expect(results(page)).toContainText(/option|opç|choose|escolh|specify/i);
    await shot(page, 'shipping-04-configurable-without-option', widget(page));
  });

  test('with the chosen option every carrier appears', async ({ page }) => {
    await openProduct(page, URL);
    await chooseConfigurable(page, '33');
    await estimate(page, FLORIANOPOLIS);

    const carriers = await readResults(page);
    expect(Object.keys(carriers).sort()).toEqual(['Best Way', 'Flat Rate']);
    await shot(page, 'shipping-05-configurable-all-carriers', widget(page));
  });

  test('the cart weight is used: 6 items x 1 kg falls in the >= 5 kg table rate (R$ 35,00)', async ({ page }) => {
    await openProduct(page, URL);
    await chooseConfigurable(page, '33');
    await page.locator('#qty').fill('6');
    await estimate(page, SAO_PAULO);

    const carriers = await readResults(page);
    expect(price(carriers['Best Way'][0])).toBe(35);
    await shot(page, 'shipping-06-configurable-weight-rate', widget(page));
  });
});

test.describe('bundle product', () => {
  test('every carrier appears once the bundle options are chosen', async ({ page }) => {
    await openProduct(page, '/sprite-yoga-companion-kit.html');
    await chooseBundleOptions(page);
    await estimate(page, FLORIANOPOLIS);

    const carriers = await readResults(page);
    expect(Object.keys(carriers).sort()).toEqual(['Best Way', 'Flat Rate']);
    await shot(page, 'shipping-07-bundle-all-carriers', widget(page));
  });
});

test.describe('endpoint', () => {
  async function post(page, fields) {
    await page.goto('/joust-duffle-bag.html', { waitUntil: 'networkidle' });
    const formKey = await page.locator('input[name="form_key"]').first().inputValue();
    return page.request.post('/fineweb/simulate/index', { form: { form_key: formKey, ...fields } });
  }

  test('rejects an invalid postcode with a JSON error object', async ({ page }) => {
    const response = await post(page, { product: '1', qty: '1', 'simulate[postcode]': '123' });
    const body = await response.json();

    expect(typeof body).toBe('object');
    expect(body.error?.message).toMatch(/postcode|CEP/i);
    expect(response.status()).toBe(400);
  });

  test('answers an unknown product with a JSON error object, not a bare string', async ({ page }) => {
    const response = await post(page, { product: '99999', qty: '1', 'simulate[postcode]': '88010400' });
    const body = await response.json();

    expect(typeof body).toBe('object');
    expect(typeof body.error?.message).toBe('string');
  });
});
