import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import tailwindcss from '@tailwindcss/vite';
export default defineConfig({
    plugins: [laravel({ input: ['resources/css/app.css', 'resources/js/app.js'], refresh: true }), tailwindcss()],
    server: { host: '0.0.0.0', port: 5173, strictPort: true, hmr: { host: process.env.VITE_HMR_HOST || 'localhost', clientPort: Number(process.env.VITE_PORT || 5178) }, watch: { ignored: ['**/storage/framework/views/**'] } },
});
