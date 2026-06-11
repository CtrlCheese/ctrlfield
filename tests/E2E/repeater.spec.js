// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * E2E: Repeater field — add rows, fill, save, reload, verify order.
 *
 * Acceptance criteria (Cycle 11):
 * - Add row → fill → save → reload → verify data and order
 * - Reorder rows (move up/down) → save → reload → verify new order
 */

const POST_TYPE_URL = '/wp-admin/post-new.php?post_type=portfolio';

test.describe('Repeater field', () => {

    test('add rows, save, reload and verify data', async ({ page }) => {
        await page.goto(POST_TYPE_URL);

        // Fill post title to make it saveable
        const titleInput = page.locator('#title, #post-title-0 input').first();
        await titleInput.fill('Repeater Test Post');

        // Wait for the FieldForge meta box to appear
        const container = page.locator('.fieldforge-container').first();
        await expect(container).toBeVisible({ timeout: 10_000 });

        // Fill client_name (required)
        await container.locator('[x-model*="client_name"]').fill('Acme Corp');

        // Click "Add Row" in the team_members repeater
        const addBtn = container.locator('.ff-btn--add').first();
        await addBtn.click();
        await addBtn.click(); // add two rows

        // Fill first row
        const firstRow = container.locator('.ff-repeater-row').nth(0);
        await firstRow.locator('[x-model*="member_name"]').fill('Alice');
        await firstRow.locator('[x-model*="member_role"]').fill('Engineer');

        // Fill second row
        const secondRow = container.locator('.ff-repeater-row').nth(1);
        await secondRow.locator('[x-model*="member_name"]').fill('Bob');
        await secondRow.locator('[x-model*="member_role"]').fill('Designer');

        // Save the post
        await page.locator('#publish, #save-post').click();
        await page.waitForLoadState('networkidle');

        // Reload
        await page.reload();
        await expect(page.locator('.fieldforge-container')).toBeVisible({ timeout: 10_000 });

        // Verify row data persisted
        const rows = page.locator('.ff-repeater-row');
        await expect(rows).toHaveCount(2);

        await expect(rows.nth(0).locator('[x-model*="member_name"]')).toHaveValue('Alice');
        await expect(rows.nth(1).locator('[x-model*="member_name"]')).toHaveValue('Bob');
    });

    test('reorder rows with move-up/down, verify order after save', async ({ page }) => {
        await page.goto(POST_TYPE_URL);

        const titleInput = page.locator('#title, #post-title-0 input').first();
        await titleInput.fill('Repeater Order Test');

        const container = page.locator('.fieldforge-container').first();
        await expect(container).toBeVisible({ timeout: 10_000 });

        await container.locator('[x-model*="client_name"]').fill('Order Corp');

        const addBtn = container.locator('.ff-btn--add').first();
        await addBtn.click();
        await addBtn.click();

        const rows = container.locator('.ff-repeater-row');
        await rows.nth(0).locator('[x-model*="member_name"]').fill('First');
        await rows.nth(1).locator('[x-model*="member_name"]').fill('Second');

        // Move second row up (it should become first)
        await rows.nth(1).locator('.ff-btn--up').click();

        // Verify order changed in the UI
        await expect(rows.nth(0).locator('[x-model*="member_name"]')).toHaveValue('Second');
        await expect(rows.nth(1).locator('[x-model*="member_name"]')).toHaveValue('First');

        // Save and reload
        await page.locator('#publish, #save-post').click();
        await page.waitForLoadState('networkidle');
        await page.reload();

        const reloadedRows = page.locator('.ff-repeater-row');
        await expect(reloadedRows.nth(0).locator('[x-model*="member_name"]')).toHaveValue('Second');
        await expect(reloadedRows.nth(1).locator('[x-model*="member_name"]')).toHaveValue('First');
    });

});
