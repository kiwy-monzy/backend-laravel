import { defineConfig } from 'vite';
import react from '@vitejs/plugin-react';

/**
 * A standalone bundle for the data grid.
 *
 * The admin deliberately has no build step — every stylesheet and script in
 * `public/` is loaded directly, which keeps deploying it a file copy. The grid
 * is the one thing that genuinely needs bundling (it is React underneath), so
 * it is built on its own into `public/js/grid.js` as a self-contained IIFE and
 * loaded exactly like the hand-written scripts beside it. Nothing else has to
 * learn about Vite, and a page without a grid pays nothing for it.
 */
export default defineConfig({
    plugins: [react()],
    define: {
        'process.env.NODE_ENV': JSON.stringify('production'),
    },
    // The output lands inside `public/`, which is also Vite's default public
    // directory; saying there isn't one stops it trying to copy the folder
    // into itself.
    publicDir: false,
    build: {
        outDir: 'public/js',
        emptyOutDir: false,
        lib: {
            entry: 'resources/js/grid/index.jsx',
            name: 'FgeGrid',
            formats: ['iife'],
            fileName: () => 'grid.js',
            // Named explicitly: package.json is `private` with no name, and Vite
            // otherwise has nothing to derive the stylesheet's filename from.
            cssFileName: 'grid',
        },
        rollupOptions: {
            output: {
                // One file, so the Blade page needs a single <script> tag.
                inlineDynamicImports: true,
                assetFileNames: 'grid.[ext]',
            },
        },
    },
});
