import { defineConfig } from 'vite';

// CSS is compiled separately via @tailwindcss/cli (see package.json build script).
// Vite handles JS only here so the CSS is always a separate file for WP enqueueing.
export default defineConfig({
    plugins: [],
    build: {
        outDir: '.',
        emptyOutDir: false,
        rollupOptions: {
            input: 'assets/admin/src/index.js',
            output: {
                entryFileNames: 'assets/admin/fieldforge.js',
                assetFileNames: 'assets/admin/[name][extname]',
                format: 'iife',
            },
        },
        minify: true,
        sourcemap: false,
    },
});
