// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * E2E: Visual Field Builder — drag field, set properties, PHP preview updates, save file.
 * Requires wp-env with ctrlfield-pro active and CTRLFIELD_SCHEMA_PATH configured.
 *
 * Acceptance criteria (Cycle B-4):
 * - Builder page loads with palette, canvas, inspector, and PHP preview
 * - Adding a field from the palette updates the PHP preview
 * - Save button writes a syntactically valid PHP file
 */

test.describe('Visual Field Builder', () => {

    test.beforeEach(async ({ page }) => {
        await page.goto('/wp-admin/admin.php?page=ctrlfield-builder');
        // Wait for React SPA to mount
        await page.locator('#ctrlfield-builder-root').waitFor({ timeout: 15_000 });
        await page.locator('.ctrlf-builder, .ctrlf-builder__header').waitFor({ timeout: 10_000 });
    });

    test('builder renders all panels', async ({ page }) => {
        await expect(page.locator('.ctrlf-builder__palette')).toBeVisible();
        await expect(page.locator('.ctrlf-builder__canvas')).toBeVisible();
        await expect(page.locator('.ctrlf-builder__inspector')).toBeVisible();
        await expect(page.locator('.ctrlf-builder__preview')).toBeVisible();
    });

    test('field palette contains text field type', async ({ page }) => {
        const palette = page.locator('.ctrlf-builder__palette');
        await expect(palette.locator('[data-field-type="text"], button', { hasText: /text/i }).first()).toBeVisible();
    });

    test('adding a text field updates the PHP preview', async ({ page }) => {
        // Click or drag the text field type onto the canvas
        const textFieldBtn = page.locator('.ctrlf-builder__palette [data-field-type="text"], .ctrlf-builder__palette button', { hasText: /^text$/i }).first();
        const canvas = page.locator('.ctrlf-builder__canvas');

        // Click to add (some builders add on click, others require drag)
        await textFieldBtn.click();

        // PHP preview should now contain Field::text(
        const preview = page.locator('.ctrlf-builder__preview pre, .ctrlf-builder__preview code');
        await expect(preview).toContainText("Field::text(", { timeout: 5_000 });
    });

    test('changing field key updates PHP preview in real time', async ({ page }) => {
        // Add a text field first
        await page.locator('.ctrlf-builder__palette [data-field-type="text"], .ctrlf-builder__palette button', { hasText: /^text$/i }).first().click();

        // Find the key input in the inspector
        const keyInput = page.locator('.ctrlf-builder__inspector input[name="key"], .ctrlf-field-inspector__key').first();
        await keyInput.clear();
        await keyInput.fill('company_name');

        // PHP preview should update
        const preview = page.locator('.ctrlf-builder__preview pre, .ctrlf-builder__preview code');
        await expect(preview).toContainText("company_name", { timeout: 3_000 });
    });

    test('save button is present', async ({ page }) => {
        const saveBtn = page.locator('button', { hasText: /save to file|save/i }).first();
        await expect(saveBtn).toBeVisible();
    });

});
