import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '')
    return {
        plugins: [vue(),tailwindcss()],
        server: {
            port: 5173,
            strictPort: true,
            proxy: {
                '/api': {
                    target: env.VITE_API_ORIGIN || 'http://localhost:8000',
                    changeOrigin: true
                }
            }
        },
        build: { outDir: 'dist', emptyOutDir: true }
    }
})