// @ts-check
const { test, expect } = require('@playwright/test');

/**
 * E2E: Admin columns — field value shown in post list table, sortable column.
 * Requires wp-env. The portfolio CPT has client_name with adminColumn(true).
 *
 * Acceptance criteria (Cycle A-4):
 * - Column appears in the post list table
 * - Clicking the column header triggers sort
 */

test.describe('Admin columns', () => {

    test('indexed field appears as column in post list table', async ({ page }) => {
        await page.goto('/wp-admin/edit.php?post_type=portfolio');

        // Column header should be present
        const columnHeader = page.locator('th#client_name, th[id="client_name"], .column-client_name');
        await expect(columnHeader).toBeVisible({ timeout: 10_000 });
    });

    test('column header is a sortable link', async ({ page }) => {
        await page.goto('/wp-admin/edit.php?post_type=portfolio');

        // Sortable column headers render as links in WP list tables
        const sortLink = page.locator('th#client_name a, .column-client_name a').first();
        await expect(sortLink).toBeVisible({ timeout: 10_000 });
        await expect(sortLink).toHaveAttribute('href', /orderby=client_name/);
    });

    test('sorted URL updates on column header click', async ({ page }) => {
        await page.goto('/wp-admin/edit.php?post_type=portfolio');

        const sortLink = page.locator('th#client_name a, .column-client_name a').first();
        await sortLink.click();
        await page.waitForLoadState('networkidle');

        expect(page.url()).toContain('orderby=client_name');
    });

});
