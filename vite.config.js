import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/filament/admin/theme.css', 'resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
        }),
        tailwindcss(),
    ],
    server: {
        cors: true,
        watch: {
            // O inotify tem um teto de watchers POR USUÁRIO (fs.inotify.max_user_watches),
            // compartilhado com todo o resto que estiver aberto, e cada DIRETÓRIO observado
            // consome um. Nenhuma das pastas abaixo é fonte de HMR, e as duas primeiras
            // sozinhas passam de 17 mil diretórios — o bastante para o dev server morrer com
            // "ENOSPC: System limit for number of file watchers reached".
            //
            // O Vite MESCLA esta lista com os ignores dele (.git, node_modules, cacheDir),
            // então isto acrescenta, não substitui.
            ignored: [
                '**/.cache/**', // resultado do PHPStan — ~10k diretórios
                '**/vendor/**', // dependências do Composer — ~7k diretórios
                '**/storage/framework/**',
                '**/public/build/**',
                '**/.phpunit.cache/**',
            ],
        },
    },
    build: {
        minify: 'terser',
        cssMinify: true,
        chunkSizeWarningLimit: 1600,
        rolldownOptions: {
            output: {
                manualChunks(id) {
                    if (id.includes('node_modules')) {
                        // Creates chunks based on the package name
                        return id.toString().split('node_modules/')[1].split('/')[0].toString();
                    }
                },
            },
        },
    },
});
