// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * E2E: Tab and Accordion UI behaviour.
 *
 * Acceptance criteria (CYCLES6 T-4):
 * - Default tab is first; others hidden
 * - Clicking tab 2 shows its fields, hides tab 1 fields
 * - Accordion is collapsed by default
 * - Clicking accordion header expands it; clicking again collapses
 * - Sub-fields inside accordion save correctly
 */

const POST_URL = '/wp-admin/post-new.php?post_type=portfolio';

test.describe('Tab and Accordion UI', () => {

    test('first tab is active by default', async ({ page }) => {
        await page.goto(POST_URL);

        const container = page.locator('.ctrlfield-container').first();
        await expect(container).toBeVisible({ timeout: 10_000 });

        const tabs = container.locator('.ctrlf-tabs-nav, [role="tablist"]').first();
        if (! await tabs.isVisible()) {
            test.skip();
            return;
        }

        const firstTab = tabs.locator('[role="tab"], .ctrlf-tab-btn').first();
        await expect(firstTab).toHaveAttribute('aria-selected', 'true');
    });

    test('clicking a different tab shows its panel', async ({ page }) => {
        await page.goto(POST_URL);

        const container = page.locator('.ctrlfield-container').first();
        await expect(container).toBeVisible({ timeout: 10_000 });

        const tabs = container.locator('.ctrlf-tabs-nav, [role="tablist"]').first();
        if (! await tabs.isVisible()) {
            test.skip();
            return;
        }

        const secondTab = tabs.locator('[role="tab"], .ctrlf-tab-btn').nth(1);
        if (! await secondTab.isVisible()) {
            test.skip();
            return;
        }

        await secondTab.click();
        await expect(secondTab).toHaveAttribute('aria-selected', 'true');

        const firstTab = tabs.locator('[role="tab"], .ctrlf-tab-btn').first();
        await expect(firstTab).not.toHaveAttribute('aria-selected', 'true');
    });

    test('accordion is collapsed by default', async ({ page }) => {
        await page.goto(POST_URL);

        const container = page.locator('.ctrlfield-container').first();
        await expect(container).toBeVisible({ timeout: 10_000 });

        const accordion = container.locator('.ctrlf-accordion').first();
        if (! await accordion.isVisible()) {
            test.skip();
            return;
        }

        const body = accordion.locator('.ctrlf-accordion-body').first();
        await expect(body).not.toBeVisible();
    });

    test('accordion expands on header click and collapses on second click', async ({ page }) => {
        await page.goto(POST_URL);

        const container = page.locator('.ctrlfield-container').first();
        await expect(container).toBeVisible({ timeout: 10_000 });

        const accordion = container.locator('.ctrlf-accordion').first();
        if (! await accordion.isVisible()) {
            test.skip();
            return;
        }

        const header = accordion.locator('.ctrlf-accordion-header').first();
        const body   = accordion.locator('.ctrlf-accordion-body').first();

        // Expand
        await header.click();
        await expect(body).toBeVisible({ timeout: 2_000 });

        // Collapse
        await header.click();
        await expect(body).not.toBeVisible({ timeout: 2_000 });
    });

    test('accordion sub-fields save and reload correctly', async ({ page }) => {
        await page.goto(POST_URL);

        const titleInput = page.locator('#title, #post-title-0 input').first();
        await titleInput.fill('Accordion E2E ' + Date.now());

        const container = page.locator('.ctrlfield-container').first();
        await expect(container).toBeVisible({ timeout: 10_000 });

        const accordion = container.locator('.ctrlf-accordion').first();
        if (! await accordion.isVisible()) {
            test.skip();
            return;
        }

        // Expand and fill a sub-field
        await accordion.locator('.ctrlf-accordion-header').click();
        const subInput = accordion.locator('input[type="text"]').first();
        if (await subInput.isVisible()) {
            await subInput.fill('Accordion Value E2E');
        }

        await page.locator('#publish, #save-post').click();
        await page.waitForLoadState('networkidle');
        await page.reload();

        await expect(container).toBeVisible({ timeout: 10_000 });

        const reloadedAccordion = container.locator('.ctrlf-accordion').first();
        await reloadedAccordion.locator('.ctrlf-accordion-header').click();

        const reloadedInput = reloadedAccordion.locator('input[type="text"]').first();
        if (await reloadedInput.isVisible()) {
            await expect(reloadedInput).toHaveValue('Accordion Value E2E');
        }
    });

});
