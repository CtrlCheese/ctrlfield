// @ts-check
const { test: setup } = require('@playwright/test');
const path = require('path');

const AUTH_FILE = path.join(__dirname, '.auth/admin.json');

/**
 * Logs in as WP admin once and saves the session to disk.
 * All other tests reuse this session via storageState.
 */
setup('authenticate as admin', async ({ page }) => {
    await page.goto('/wp-login.php');

    await page.fill('#user_login', 'admin');
    await page.fill('#user_pass', 'password');
    await page.click('#wp-submit');

    await page.waitForURL('**/wp-admin/**');

    await page.context().storageState({ path: AUTH_FILE });
});
