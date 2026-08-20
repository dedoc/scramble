import { mkdirSync, rmSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';

const dist = resolve(import.meta.dirname, 'dist');
const hotFile = resolve(dist, 'hot');
const removeHotFile = () => rmSync(hotFile, { force: true });
type HotFileLifecycle = { registered: boolean };
const globalWithHotFileLifecycle = globalThis as typeof globalThis & {
    __scrambleHotFileLifecycle?: HotFileLifecycle;
};
const hotFileLifecycle = (globalWithHotFileLifecycle.__scrambleHotFileLifecycle ??= {
    registered: false,
});

if (!hotFileLifecycle.registered) {
    process.once('exit', removeHotFile);
    process.once('SIGINT', () => process.exit(130));
    process.once('SIGTERM', () => process.exit(143));
    hotFileLifecycle.registered = true;
}

export default defineConfig(({ command }) => ({
    define: {
        'process.env.NODE_ENV': JSON.stringify(command === 'build' ? 'production' : 'development'),
    },
    server: {
        cors: true,
        strictPort: true,
    },
    optimizeDeps: {
        entries: ['resources/js/devtools.tsx'],
        include: [
            'react',
            'react/jsx-dev-runtime',
            'react/jsx-runtime',
            'react-dom',
            'react-dom/client',
        ],
    },
    plugins: [
        react(),
        tailwindcss(),
        {
            name: 'scramble-hot-file',
            configureServer(server) {
                server.httpServer?.once('listening', () => {
                    const localUrl = server.resolvedUrls?.local[0];
                    if (!localUrl) {
                        return;
                    }

                    mkdirSync(dist, { recursive: true });
                    writeFileSync(hotFile, localUrl);
                });
            },
        },
    ],
    build: {
        outDir: dist,
        emptyOutDir: true,
        minify: 'oxc',
        rolldownOptions: {
            output: {
                minify: {
                    compress: true,
                    mangle: true,
                    codegen: true,
                },
                comments: false,
            },
        },
        lib: {
            entry: resolve(import.meta.dirname, 'resources/js/devtools.tsx'),
            formats: ['es'],
            fileName: 'devtools',
        },
    },
}));
