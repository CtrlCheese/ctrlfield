// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * E2E: WYSIWYG field — type content, save, reload, verify content retrieved.
 *
 * Acceptance criteria (Cycle 11):
 * - Type in TinyMCE → save → reload → verify content is stored and loaded
 */

test.describe('WYSIWYG field', () => {

    test('type content, save, reload and verify it persists', async ({ page }) => {
        await page.goto('/wp-admin/post-new.php?post_type=portfolio');

        const titleInput = page.locator('#title, #post-title-0 input').first();
        await titleInput.fill('WYSIWYG Test Post');

        const container = page.locator('.ctrlfield-container').first();
        await expect(container).toBeVisible({ timeout: 10_000 });

        await container.locator('[x-model*="client_name"]').fill('WYSIWYG Client');

        // TinyMCE renders inside an iframe — interact with the iframe body
        const editorId = 'ctrlf_wysiwyg_description';  // adjust key to match your dev-test schema
        const editorFrame = page.frameLocator(`#${editorId}_ifr`);

        // Wait for TinyMCE to initialise
        await expect(editorFrame.locator('body#tinymce')).toBeVisible({ timeout: 10_000 });

        // Click inside and type
        await editorFrame.locator('body#tinymce').click();
        await editorFrame.locator('body#tinymce').fill('Hello from WYSIWYG');

        // Trigger TinyMCE's change event so Alpine picks up the content
        await editorFrame.locator('body#tinymce').dispatchEvent('keyup');

        // Save
        await page.locator('#publish, #save-post').click();
        await page.waitForLoadState('networkidle');

        // Reload
        await page.reload();
        await expect(container).toBeVisible({ timeout: 10_000 });

        // Re-open TinyMCE and verify content
        const reloadedFrame = page.frameLocator(`#${editorId}_ifr`);
        await expect(reloadedFrame.locator('body#tinymce')).toContainText('Hello from WYSIWYG');
    });

    test('wysiwyg content updates admin state on every keystroke', async ({ page }) => {
        await page.goto('/wp-admin/post-new.php?post_type=portfolio');

        const container = page.locator('.ctrlfield-container').first();
        await expect(container).toBeVisible({ timeout: 10_000 });

        const editorId = 'ctrlf_wysiwyg_description';
        const editorFrame = page.frameLocator(`#${editorId}_ifr`);
        await expect(editorFrame.locator('body#tinymce')).toBeVisible({ timeout: 10_000 });

        await editorFrame.locator('body#tinymce').click();
        await editorFrame.locator('body#tinymce').type('Live sync test');

        // The hidden payload input should include the typed content
        const payload = await page.evaluate(() => {
            const input = document.querySelector('input[name="ctrlfield_payload"]');
            return input ? JSON.parse(input.value) : null;
        });

        expect(payload).not.toBeNull();
        // The description key should contain the typed text
        expect(JSON.stringify(payload)).toContain('Live sync test');
    });

});
