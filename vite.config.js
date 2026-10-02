import {defineConfig} from 'vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [react()],
    publicDir: false,
    build: {
        outDir: 'public/build',
        emptyOutDir: true,
        manifest: 'manifest.json',
        rollupOptions: {
            input: {
                app: 'resources/js/App.jsx',
                learn: 'resources/css/learn.css',
            },
            output: {
                manualChunks(id) {
                    if (id.includes('node_modules/react') || id.includes('node_modules/react-dom')) return 'react';
                    if (id.includes('node_modules/lucide-react')) return 'icons';
                },
                chunkFileNames: 'assets/[name]-[hash].js',
                entryFileNames: 'assets/[name]-[hash].js',
                assetFileNames: 'assets/[name]-[hash][extname]',
            },
        },
    },
    server: {
        origin: 'http://127.0.0.1:5173',
    },
});
