// The WordPress "Insert/edit link" dialog of WYSIWYG editors (wpLink), with
// the Link field's browser: tabs per post type / taxonomy and page anchors,
// and results that show type, status, date and thumbnail. The dialog itself
// is untouched: picking a result fills its URL and link text, and WordPress
// inserts the link as usual.

import { linkBrowser } from './link.js';

let browser = null;
let autoText = '';

function setUp(config) {
    const panel = document.getElementById('search-panel');
    const search = document.getElementById('wp-link-search');
    const url = document.getElementById('wp-link-url');
    const text = document.getElementById('wp-link-text');
    if (!panel || !search || !url) return null;

    // WordPress' search field (pre-filled with the selected text) now searches
    // the active tab; its own result lists are hidden by CSS.
    const choose = (item) => {
        url.value = item.url;
        if (text && (text.value === '' || text.value === autoText)) {
            text.value = item.title;
            autoText = item.title;
        }
        b.markSelected(item.url);
    };
    const b = linkBrowser(config, {
        search,
        onChoose: choose,
        onConfirm: (item) => { choose(item); window.wpLink?.update?.(); },
        selectedUrl: () => url.value,
    });

    const wrap = document.createElement('div');
    wrap.className = 'ctrlf-wplink';
    wrap.append(b.tabBar, b.list, b.status);
    panel.classList.add('ctrlf-wplink-panel');
    panel.append(wrap);
    url.addEventListener('input', () => b.markSelected(url.value));
    return b;
}

export function registerWpLinkTabs() {
    const config = window.ctrlfieldData?.wplink;
    const $ = window.jQuery;
    if (!config || !$) return;

    $(document).on('wplink-open', () => {
        browser ??= setUp(config);
        if (!browser) return;
        autoText = '';
        browser.start();
    });
}
