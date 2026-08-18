import { mkdirSync, rmSync, writeFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { defineConfig } from 'vite';

const dist = resolve(import.meta.dirname, 'dist');
const hotFile = resolve(dist, 'hot');
const removeHotFile = () => rmSync(hotFile, { force: true });

export default defineConfig({
    server: {
        cors: true,
        strictPort: true,
    },
    plugins: [
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
        lib: {
            entry: resolve(import.meta.dirname, 'resources/js/devtools.js'),
            formats: ['es'],
            fileName: 'devtools',
            cssFileName: 'devtools',
        },
    },
});
