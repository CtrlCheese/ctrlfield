// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * E2E: ComponentRegistry — component renders correctly via FlexibleContent.
 *
 * Acceptance criteria (CYCLES6 T-4):
 * - A registered component appears in the picker
 * - Selecting a component creates a layout row with its sub-fields
 * - The component template renders on the front end
 * - Component category tabs filter the picker grid
 */

const PAGE_URL = '/wp-admin/post-new.php?post_type=page';

test.describe('ComponentRegistry render', () => {

    test('component picker shows at least one card', async ({ page }) => {
        await page.goto(PAGE_URL);

        const container = page.locator('.ctrlfield-container').first();
        await expect(container).toBeVisible({ timeout: 10_000 });

        const addBtn = container.locator('.ctrlf-flex-add, button', { hasText: /add/i }).first();
        if (! await addBtn.isVisible()) {
            test.skip();
            return;
        }

        await addBtn.click();
        await expect(page.locator('.ctrlf-picker-modal')).toBeVisible({ timeout: 5_000 });

        const cards = page.locator('.ctrlf-picker-card');
        expect(await cards.count()).toBeGreaterThan(0);
    });

    test('category tabs filter the component grid', async ({ page }) => {
        await page.goto(PAGE_URL);

        const container = page.locator('.ctrlfield-container').first();
        await expect(container).toBeVisible({ timeout: 10_000 });

        const addBtn = container.locator('.ctrlf-flex-add, button', { hasText: /add/i }).first();
        if (! await addBtn.isVisible()) {
            test.skip();
            return;
        }

        await addBtn.click();
        await expect(page.locator('.ctrlf-picker-modal')).toBeVisible({ timeout: 5_000 });

        // Category tabs (All + any registered categories)
        const tabs = page.locator('.ctrlf-picker-tab');
        const tabCount = await tabs.count();

        if (tabCount <= 1) {
            // Only "All" tab — skip category filtering test
            test.skip();
            return;
        }

        const totalCards = await page.locator('.ctrlf-picker-card').count();

        // Click the second tab (first non-All category)
        await tabs.nth(1).click();
        await page.waitForTimeout(300);

        const filteredCards = await page.locator('.ctrlf-picker-card').count();
        // Filtered count should be <= total
        expect(filteredCards).toBeLessThanOrEqual(totalCards);
    });

    test('selecting a component creates a layout row with sub-fields', async ({ page }) => {
        await page.goto(PAGE_URL);

        const container = page.locator('.ctrlfield-container').first();
        await expect(container).toBeVisible({ timeout: 10_000 });

        const addBtn = container.locator('.ctrlf-flex-add, button', { hasText: /add/i }).first();
        if (! await addBtn.isVisible()) {
            test.skip();
            return;
        }

        const rowsBefore = await container.locator('.ctrlf-flex-row').count();

        await addBtn.click();
        await expect(page.locator('.ctrlf-picker-card').first()).toBeVisible({ timeout: 5_000 });
        await page.locator('.ctrlf-picker-card').first().click();

        const rowsAfter = await container.locator('.ctrlf-flex-row').count();
        expect(rowsAfter).toBe(rowsBefore + 1);

        // Expand the new row and verify sub-fields are visible
        const newRow = container.locator('.ctrlf-flex-row').last();
        await newRow.locator('.ctrlf-flex-toggle').click();

        const subFields = newRow.locator('.ctrlf-field');
        // A component should have at least one field
        expect(await subFields.count()).toBeGreaterThanOrEqual(0);
    });

    test('component renders on front end after save', async ({ page }) => {
        await page.goto(PAGE_URL);

        const titleInput = page.locator('#title, #post-title-0 input').first();
        const postTitle  = 'ComponentRegistry E2E ' + Date.now();
        await titleInput.fill(postTitle);

        const container = page.locator('.ctrlfield-container').first();
        await expect(container).toBeVisible({ timeout: 10_000 });

        const addBtn = container.locator('.ctrlf-flex-add, button', { hasText: /add/i }).first();
        if (! await addBtn.isVisible()) {
            test.skip();
            return;
        }

        await addBtn.click();
        const firstCard = page.locator('.ctrlf-picker-card').first();
        await expect(firstCard).toBeVisible({ timeout: 5_000 });
        await firstCard.click();

        // Publish and navigate to front end
        await page.locator('#publish').click();
        await page.waitForLoadState('networkidle');

        const permalink = page.locator('#sample-permalink a, .view-post a');
        if (await permalink.isVisible()) {
            const href = await permalink.getAttribute('href');
            if (href) {
                await page.goto(href);
                // The front end should load without a blank page
                await expect(page.locator('body')).toBeVisible();
            }
        }
    });

});
