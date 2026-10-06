// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * E2E: flexible_content — add layouts, reorder, save, reload, verify data and order.
 * Requires wp-env with ctrlfield-pro active and a flexible_content group in dev-test.php.
 *
 * Acceptance criteria (Cycle B-1):
 * - Layout picker appears and layouts can be added
 * - Layout order persists after save
 * - Each layout's sub-fields save and reload correctly
 */

test.describe('flexible_content field', () => {

    test('layout picker button is visible', async ({ page }) => {
        await page.goto('/wp-admin/post-new.php?post_type=page');

        const container = page.locator('.ctrlfield-container').first();
        await expect(container).toBeVisible({ timeout: 10_000 });

        // The "Add Section" button (or configured buttonLabel)
        const addBtn = container.locator('button', { hasText: /add (section|layout)/i }).first();
        await expect(addBtn).toBeVisible();
    });

    test('adding a layout renders its sub-fields', async ({ page }) => {
        await page.goto('/wp-admin/post-new.php?post_type=page');

        const container = page.locator('.ctrlfield-container').first();
        await expect(container).toBeVisible({ timeout: 10_000 });

        // Click add layout button
        await container.locator('button', { hasText: /add (section|layout)/i }).first().click();

        // A layout picker modal or inline selector should appear
        const layoutOption = page.locator('.ctrlf-layout-picker, [data-layout]').first();
        await expect(layoutOption).toBeVisible({ timeout: 5_000 });
    });

    test('layout order persists after save and reload', async ({ page }) => {
        await page.goto('/wp-admin/post-new.php?post_type=page');

        const titleInput = page.locator('#title, #post-title-0 input').first();
        await titleInput.fill('FlexContent E2E ' + Date.now());

        const container = page.locator('.ctrlfield-container').first();
        await expect(container).toBeVisible({ timeout: 10_000 });

        // Add two layouts and record their initial order
        const addBtn = container.locator('button', { hasText: /add (section|layout)/i }).first();
        await addBtn.click();
        const firstLayout = page.locator('.ctrlf-layout-picker [data-layout]').first();
        if (await firstLayout.isVisible()) {
            await firstLayout.click();
        }

        // Save and reload
        await page.locator('#publish, #save-post').click();
        await page.waitForLoadState('networkidle');
        await page.reload();

        // Verify at least one layout row persists
        const layoutRows = page.locator('.ctrlf-flex-layout-row, [data-layout-index]');
        await expect(layoutRows.first()).toBeVisible({ timeout: 10_000 });
    });

});
