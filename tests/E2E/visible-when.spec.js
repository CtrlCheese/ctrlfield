// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * E2E: visibleWhen — dependent field appears/hides reactively.
 *
 * Acceptance criteria (Cycle 11):
 * - Select condition field value → verify dependent field appears
 * - Clear condition → verify dependent field hides
 * - No page reload required
 */

test.describe('visibleWhen conditional fields', () => {

    test('dependent field shows when condition is met', async ({ page }) => {
        await page.goto('/wp-admin/post-new.php?post_type=portfolio');

        const container = page.locator('.ctrlfield-container').first();
        await expect(container).toBeVisible({ timeout: 10_000 });

        // The client_contact field is visible only when project_type == 'web'
        const dependentField = container.locator('[x-show*="client_contact"]');

        // Initially — project_type is '' → client_contact should be hidden
        await expect(dependentField).not.toBeVisible();

        // Select 'web' in project_type
        await container.locator('[x-model*="project_type"]').selectOption('web');

        // client_contact should now appear (no page reload)
        await expect(dependentField).toBeVisible();
    });

    test('dependent field hides when condition clears', async ({ page }) => {
        await page.goto('/wp-admin/post-new.php?post_type=portfolio');

        const container = page.locator('.ctrlfield-container').first();
        await expect(container).toBeVisible({ timeout: 10_000 });

        const select = container.locator('[x-model*="project_type"]');
        const dependentField = container.locator('[x-show*="client_contact"]');

        // Show it first
        await select.selectOption('web');
        await expect(dependentField).toBeVisible();

        // Change to a different value — field should hide
        await select.selectOption('brand');
        await expect(dependentField).not.toBeVisible();
    });

    test('hidden field value is preserved after save', async ({ page }) => {
        await page.goto('/wp-admin/post-new.php?post_type=portfolio');

        const titleInput = page.locator('#title, #post-title-0 input').first();
        await titleInput.fill('Visible When Test');

        const container = page.locator('.ctrlfield-container').first();
        await expect(container).toBeVisible({ timeout: 10_000 });

        await container.locator('[x-model*="client_name"]').fill('Acme');

        // Show and fill the dependent field
        await container.locator('[x-model*="project_type"]').selectOption('web');
        const contactInput = container.locator('[x-model*="client_contact"]');
        await expect(contactInput).toBeVisible();
        await contactInput.fill('contact@acme.com');

        // Hide it again before saving
        await container.locator('[x-model*="project_type"]').selectOption('brand');
        await expect(contactInput).not.toBeVisible();

        // Save
        await page.locator('#publish, #save-post').click();
        await page.waitForLoadState('networkidle');
        await page.reload();

        // Show the field again and verify the value was preserved
        await container.locator('[x-model*="project_type"]').selectOption('web');
        await expect(container.locator('[x-model*="client_contact"]')).toHaveValue('contact@acme.com');
    });

});
