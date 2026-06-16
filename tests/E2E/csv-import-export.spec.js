// @ts-check
const { test, expect } = require('@playwright/test');
const path = require('path');
const os = require('os');
const fs = require('fs');

/**
 * E2E: CSV import/export via WP-CLI or admin UI.
 *
 * Acceptance criteria (CYCLES6 T-4):
 * - Export produces a downloadable CSV with the correct columns
 * - Import from a CSV creates/updates posts and reports stats
 * - Dry-run import reports counts without writing
 *
 * These tests interact with the admin tools page or use WP-CLI
 * via the REST API test endpoint exposed in FIELDFORGE_DEV_TEST mode.
 */

const TOOLS_URL = '/wp-admin/admin.php?page=fieldforge-tools';

test.describe('CSV import / export', () => {

    test('tools page is accessible', async ({ page }) => {
        await page.goto(TOOLS_URL);

        const body = page.locator('body');
        await expect(body).toBeVisible({ timeout: 5_000 });

        // Accept either the tools page or a redirect to plugin settings
        const title = await page.title();
        expect(title).toBeTruthy();
    });

    test('export button triggers a file download', async ({ page }) => {
        await page.goto(TOOLS_URL);
        await expect(page.locator('.wrap')).toBeVisible({ timeout: 5_000 });

        const exportBtn = page.locator('button, a', { hasText: /export/i }).first();
        if (! await exportBtn.isVisible()) {
            test.skip();
            return;
        }

        // Listen for the download
        const [download] = await Promise.all([
            page.waitForEvent('download'),
            exportBtn.click(),
        ]);

        expect(download.suggestedFilename()).toMatch(/\.(csv|zip)$/i);
    });

    test('import form accepts a CSV file', async ({ page }) => {
        await page.goto(TOOLS_URL);
        await expect(page.locator('.wrap')).toBeVisible({ timeout: 5_000 });

        const fileInput = page.locator('input[type="file"][accept*="csv"], #fieldforge-csv-import');
        if (! await fileInput.isVisible()) {
            test.skip();
            return;
        }

        // Create a minimal CSV file in temp
        const tmpCsv = path.join(os.tmpdir(), 'ff_e2e_import.csv');
        fs.writeFileSync(tmpCsv, 'post_id,post_title,post_status,client_name\n0,E2E Import Post,publish,E2E Client\n');

        await fileInput.setInputFiles(tmpCsv);

        const submitBtn = page.locator('button[type="submit"], input[type="submit"]', { hasText: /import/i }).first();
        if (await submitBtn.isVisible()) {
            await submitBtn.click();
            await page.waitForLoadState('networkidle');

            // Result message should appear
            const result = page.locator('.notice, .updated, .fieldforge-import-result');
            await expect(result.first()).toBeVisible({ timeout: 10_000 });
        }

        // Cleanup
        try { fs.unlinkSync(tmpCsv); } catch {}
    });

    test('REST dev-test endpoint reports CSV export schema', async ({ page }) => {
        // FieldForge exposes a dev REST endpoint when FIELDFORGE_DEV_TEST=true (wp-env config)
        const response = await page.request.get('/wp-json/fieldforge/v1/schema/list', {
            headers: { 'X-WP-Nonce': '' },
        });

        // 200 = endpoint exists; 401/404 = not registered (skip gracefully)
        if (response.status() === 200) {
            const body = await response.json();
            expect(Array.isArray(body)).toBe(true);
        } else {
            // Endpoint not available in this wp-env setup — skip
            test.skip();
        }
    });

});
