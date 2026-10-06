// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * E2E: map field — set marker, save, reload, verify stored lat/lng.
 * Requires wp-env with ctrlfield-pro active and a map field group.
 * Uses OpenStreetMap provider (no API key required).
 *
 * Acceptance criteria (Cycle B-5):
 * - Map renders with a preview container
 * - After setting coordinates and saving, {lat, lng, zoom} persists
 */

test.describe('map field', () => {

    test('map field renders a map preview container', async ({ page }) => {
        await page.goto('/wp-admin/post-new.php?post_type=portfolio');

        const container = page.locator('.ctrlfield-container').first();
        await expect(container).toBeVisible({ timeout: 10_000 });

        const mapField = container.locator('.ctrlf-field-map, [data-field-type="map"]').first();
        await expect(mapField).toBeVisible();
    });

    test('map coordinates persist after save and reload', async ({ page }) => {
        await page.goto('/wp-admin/post-new.php?post_type=portfolio');

        const titleInput = page.locator('#title, #post-title-0 input').first();
        await titleInput.fill('Map E2E Test ' + Date.now());

        // Set coordinates via Alpine state
        await page.evaluate(() => {
            const el = document.querySelector('.ctrlfield-container');
            if (el && el._x_dataStack) {
                el._x_dataStack[0].adminState.location = {
                    lat: 48.8566,
                    lng: 2.3522,
                    zoom: 12,
                    address: 'Paris, France',
                };
            }
        });

        // Save
        await page.locator('#publish, #save-post').click();
        await page.waitForLoadState('networkidle');
        await page.reload();

        // Verify lat/lng are stored by checking the lat input rendered by MapRenderer
        const latInput = page.locator('.ctrlfield-container [name*="lat"], .ctrlf-map-lat').first();
        await expect(latInput).toHaveValue('48.8566', { timeout: 10_000 });
    });

});
