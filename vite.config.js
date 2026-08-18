import { mkdirSync, rmSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import { defineConfig } from 'vite';

const dist = resolve(import.meta.dirname, 'dist');
const hotFile = resolve(dist, 'hot');
const removeHotFile = () => rmSync(hotFile, { force: true });

export default defineConfig(({ command }) => ({
    define: {
        'process.env.NODE_ENV': JSON.stringify(command === 'build' ? 'production' : 'development'),
    },
    server: {
        cors: true,
        strictPort: true,
    },
    plugins: [
        react(),
        tailwindcss(),
        {
            name: 'scramble-hot-file',
            configureServer(server) {
                server.httpServer?.once('listening', () => {
                    mkdirSync(dist, { recursive: true });
                    writeFileSync(hotFile, server.resolvedUrls.local[0]);
                });
                server.httpServer?.once('close', removeHotFile);
                process.once('exit', removeHotFile);
                process.once('SIGINT', () => process.exit(130));
                process.once('SIGTERM', () => process.exit(143));
            },
            closeBundle() {
                removeHotFile();
            },
        },
    ],
    build: {
        outDir: dist,
        emptyOutDir: true,
        minify: 'oxc',
        cssCodeSplit: true,
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
            entry: [
                resolve(import.meta.dirname, 'resources/js/devtools.js'),
                resolve(import.meta.dirname, 'resources/js/devtools.css'),
            ],
            formats: ['es'],
            fileName: (_, entryName) => `${entryName}.js`,
            cssFileName: 'devtools',
        },
    },
}));
