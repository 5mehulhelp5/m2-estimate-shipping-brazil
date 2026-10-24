import { expect } from '@playwright/test';

/** Folder for screenshots: test-results/screenshots/<phase>/ ; phase is "before" or "after" the fixes. */
export const PHASE = process.env.QA_PHASE ?? 'after';
/** Screenshot of the page, or of one element when `target` is given (so the result is not left below the fold). */
export const shot = async (page, name, target = page) => {
  await page.waitForTimeout(700); // let slide / fade animations finish, so the element is captured at its final size
  return target.screenshot({ path: `test-results/screenshots/${PHASE}/${name}.png` });
};

/** "R$ 1.234,56" -> 1234.56 (pt_BR price format). */
export const parseBRL = (text) => parseFloat(text.replace(/[^\d,]/g, '').replace(',', '.'));

export function collectPageErrors(page) {
  const errors = [];
  page.on('pageerror', (error) => errors.push(error.message));
  return errors;
}

/** Open a product page and wait until the shipping widget script is running (the button is server-rendered). */
export async function openProduct(page, url) {
  await page.goto(url);
  await expect(page.locator('.simulate-container')).toBeVisible();
  await page.waitForFunction(() => window.require?.defined?.('Fineweb_SimulateProductShipping/js/fineweb-shipping'));
}

export const postcodeInput = (page) => page.locator('input[name="simulate[postcode]"]');
export const results = (page) => page.locator('#shipping-estimate-results');

/** Type a postcode and click "Estimate shipping". */
export async function estimate(page, postcode) {
  await postcodeInput(page).fill('');
  await postcodeInput(page).pressSequentially(postcode);
  await page.locator('#btn-simulate').click();
}

/**
 * What the results list shows, as { "Carrier title": [{ label, price }] }.
 * Each method row reads "<label> - <price>[ - <message>]".
 */
export async function readResults(page) {
  await expect(results(page).locator('li').first()).toBeVisible();
  return page.$$eval('#shipping-estimate-results > li', (carriers) =>
    Object.fromEntries(
      carriers.map((carrier) => [
        carrier.querySelector('.title')?.textContent.trim() ?? carrier.textContent.trim(),
        [...carrier.querySelectorAll('ul > li')].map((method) => {
          const [label, ...rest] = method.textContent.split(' - ');
          return { label: label.trim(), price: rest.join(' - ').trim() };
        }),
      ]),
    ),
  );
}

/** Choose the first value of every required radio group of a bundle. */
export async function chooseBundleOptions(page) {
  for (const group of await page.locator('.bundle-options-container .field.option.required').all()) {
    const radio = group.locator('input[type="radio"]').first();
    if (await radio.count()) await radio.check();
  }
}

/** Choose a configurable's color and size swatches. */
export async function chooseConfigurable(page, size) {
  await page.locator('.swatch-attribute.color .swatch-option').first().click();
  await page.locator(`.swatch-attribute.size .swatch-option:text-is("${size}")`).click();
}
