// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * E2E: User meta fields — FieldForge fields on the WP user profile page.
 * Requires wp-env with a user_profile field group registered in dev-test.php.
 *
 * Acceptance criteria (Cycle A-3):
 * - Fields render on the edit user screen
 * - Saving the profile stores data in wp_usermeta
 * - Reloading the page shows the saved value
 */

test.describe('User meta fields', () => {

    test('fieldforge meta box renders on user profile page', async ({ page }) => {
        await page.goto('/wp-admin/profile.php');

        // FieldForge adds a fieldset or section to the profile form
        const ffSection = page.locator('.fieldforge-container, [id*="fieldforge"]').first();
        await expect(ffSection).toBeVisible({ timeout: 10_000 });
    });

    test('user field value is saved and reloads correctly', async ({ page }) => {
        await page.goto('/wp-admin/profile.php');

        const container = page.locator('.fieldforge-container').first();
        await expect(container).toBeVisible({ timeout: 10_000 });

        // Find any text input inside the FieldForge container and fill it
        const textInput = container.locator('input[type="text"]').first();
        const testValue = 'E2E User Test ' + Date.now();
        await textInput.fill(testValue);

        // Save the profile
        await page.locator('#submit').click();
        await page.waitForLoadState('networkidle');

        // Reload and verify the value is still there
        await page.goto('/wp-admin/profile.php');
        const reloadedInput = page.locator('.fieldforge-container input[type="text"]').first();
        await expect(reloadedInput).toHaveValue(testValue);
    });

});
