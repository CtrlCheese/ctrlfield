#!/usr/bin/env node
/**
 * Builds the icon sets the Icon field offers, as static JSON the admin fetches
 * on demand and PHP reads to print icons — no request leaves the site.
 *
 *   assets/icons/lucide.json     { version, icons: { name: "<svg body>" }, tags: { name: "words" }, aliases: { old: name } }
 *   assets/icons/dashicons.json  { version, icons: [name, …] }
 *
 * Usage: node bin/build-icons.mjs [path/to/wp-includes/css/dashicons.css]
 * Without a path, dashicons.json is left as it is.
 */
import { readFileSync, readdirSync, writeFileSync, existsSync } from 'node:fs';
import { join, dirname } from 'node:path';
import { fileURLToPath } from 'node:url';

const root = join(dirname(fileURLToPath(import.meta.url)), '..');
const out  = join(root, 'assets/icons');

// Lucide ------------------------------------------------------------------
const pkg   = join(root, 'node_modules/lucide-static');
const meta  = JSON.parse(readFileSync(join(pkg, 'package.json'), 'utf8'));
const tags  = JSON.parse(readFileSync(join(pkg, 'tags.json'), 'utf8'));
const icons = {};
const words = {};

for (const file of readdirSync(join(pkg, 'icons')).filter((f) => f.endsWith('.svg')).sort()) {
    const name = file.slice(0, -4);
    const svg  = readFileSync(join(pkg, 'icons', file), 'utf8');
    const body = svg
        .replace(/<!--[\s\S]*?-->/g, '')
        .replace(/^[\s\S]*?<svg[^>]*>/, '')
        .replace(/<\/svg>[\s\S]*$/, '')
        .replace(/\s+/g, ' ')
        .replace(/>\s+</g, '><')
        .replace(/\s*\/>/g, '/>')
        .trim();
    icons[name] = body;
    if (Array.isArray(tags[name]) && tags[name].length) {
        words[name] = tags[name].join(' ');
    }
}

// Old names Lucide still exports (AlertCircle → circle-alert): a value saved
// before a rename keeps working.
const aliases = {};
const kebab = (pascal) => pascal
    .replace(/([a-z0-9])([A-Z])/g, '$1-$2')
    .replace(/([A-Z])([A-Z][a-z])/g, '$1-$2')
    .replace(/([a-zA-Z])(\d)/g, '$1-$2')
    .toLowerCase();
const esm = readFileSync(join(pkg, 'dist/esm/lucide-static.mjs'), 'utf8');
for (const [, names, file] of esm.matchAll(/export \{([^}]+)\} from '\.\/icons\/([a-z0-9-]+)\.mjs'/g)) {
    if (!icons[file]) continue;
    for (const [, pascal] of names.matchAll(/default as (\w+)/g)) {
        const old = kebab(pascal);
        if (old !== file && !icons[old]) aliases[old] = file;
    }
}

writeFileSync(join(out, 'lucide.json'), JSON.stringify({ version: meta.version, icons, tags: words, aliases }));
console.log(`lucide.json: ${Object.keys(icons).length} icons, ${Object.keys(aliases).length} old names (v${meta.version})`);

// Dashicons ---------------------------------------------------------------
const css = process.argv[2];
if (css && existsSync(css)) {
    const names = [...new Set(
        [...readFileSync(css, 'utf8').matchAll(/\.dashicons-([a-z0-9-]+):before/g)].map((m) => m[1])
    )].sort();
    const version = (readFileSync(css, 'utf8').match(/dashicons[^\n]*?(\d+\.\d+(\.\d+)?)/) ?? [])[1] ?? '';
    writeFileSync(join(out, 'dashicons.json'), JSON.stringify({ version, icons: names }));
    console.log(`dashicons.json: ${names.length} icons`);
}
