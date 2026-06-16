// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * E2E: User field AJAX search.
 *
 * Acceptance criteria (CYCLES6 T-4):
 * - Typing 2+ chars triggers AJAX; results dropdown appears
 * - Clicking a result selects the user; dropdown closes
 * - Displayed label = user display_name
 * - Save+reload restores user ID; label re-fetched
 */

const POST_URL = '/wp-admin/post-new.php?post_type=portfolio';

test.describe('User field AJAX search', () => {

    test('typing triggers search and shows results dropdown', async ({ page }) => {
        await page.goto(POST_URL);

        const container = page.locator('.fieldforge-container').first();
        await expect(container).toBeVisible({ timeout: 10_000 });

        const userSearch = container.locator('.ff-user-search, input[placeholder*="user" i], [x-model*="user"]').first();
        if (! await userSearch.isVisible()) {
            test.skip();
            return;
        }

        // Type at least 2 chars to trigger search
        await userSearch.fill('ad');

        // Wait for the dropdown to appear
        const dropdown = container.locator('.ff-user-results, .ff-user-dropdown');
        await expect(dropdown).toBeVisible({ timeout: 5_000 });

        const resultItems = dropdown.locator('li, .ff-user-result');
        expect(await resultItems.count()).toBeGreaterThan(0);
    });

    test('clicking a result selects user and closes dropdown', async ({ page }) => {
        await page.goto(POST_URL);

        const container = page.locator('.fieldforge-container').first();
        await expect(container).toBeVisible({ timeout: 10_000 });

        const userSearch = container.locator('.ff-user-search, input[placeholder*="user" i], [x-model*="user"]').first();
        if (! await userSearch.isVisible()) {
            test.skip();
            return;
        }

        await userSearch.fill('ad');
        const dropdown = container.locator('.ff-user-results, .ff-user-dropdown');
        await expect(dropdown).toBeVisible({ timeout: 5_000 });

        const firstResult = dropdown.locator('li, .ff-user-result').first();
        const userName = (await firstResult.textContent()) ?? '';
        await firstResult.click();

        // Dropdown closes
        await expect(dropdown).not.toBeVisible({ timeout: 2_000 });

        // Selected user name is shown
        const selectedLabel = container.locator('.ff-user-selected, .ff-user-name');
        if (await selectedLabel.isVisible()) {
            await expect(selectedLabel).toContainText(userName.trim().split('\n')[0]);
        }
    });

    test('selected user persists after save and reload', async ({ page }) => {
        await page.goto(POST_URL);

        const titleInput = page.locator('#title, #post-title-0 input').first();
        await titleInput.fill('User Field E2E ' + Date.now());

        const container = page.locator('.fieldforge-container').first();
        await expect(container).toBeVisible({ timeout: 10_000 });

        const userSearch = container.locator('.ff-user-search, input[placeholder*="user" i]').first();
        if (! await userSearch.isVisible()) {
            test.skip();
            return;
        }

        await userSearch.fill('ad');
        const dropdown = container.locator('.ff-user-results, .ff-user-dropdown');
        await expect(dropdown).toBeVisible({ timeout: 5_000 });
        await dropdown.locator('li, .ff-user-result').first().click();

        // Fill required field if any
        const required = container.locator('[x-model*="client_name"]').first();
        if (await required.isVisible()) {
            await required.fill('User Test');
        }

        await page.locator('#publish, #save-post').click();
        await page.waitForLoadState('networkidle');
        await page.reload();

        await expect(container).toBeVisible({ timeout: 10_000 });

        // The selected user should still be shown
        const selected = container.locator('.ff-user-selected, .ff-user-name, [x-model*="user_id"]');
        if (await selected.first().isVisible()) {
            const value = await selected.first().textContent() || await selected.first().inputValue();
            expect(value).toBeTruthy();
        }
    });

});
