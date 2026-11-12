import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: true,
            fonts: [
                // Body text
                bunny('Sora', {
                    weights: [300, 400, 500, 600],
                    preload: [{ weight: 400 }, { weight: 600 }],
                    optimizedFallbacks: false,
                }),
                // Headings and big numbers
                bunny('Cormorant Upright', {
                    weights: [400, 500, 600],
                    preload: [{ weight: 400 }],
                    optimizedFallbacks: false,
                }),
                // Only for the taka sign: Sora and Cormorant have no Bengali glyphs.
                // The browser downloads it only on pages that show the sign.
                bunny('Hind Siliguri', {
                    weights: [400, 600],
                    subsets: ['bengali'],
                    preload: false,
                    optimizedFallbacks: false,
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
