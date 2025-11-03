import { defineConfig, loadEnv } from 'vite'
import vue from '@vitejs/plugin-vue'
import tailwindcss from '@tailwindcss/vite'
import path from 'path'
import fs from 'fs'

export default defineConfig(({ mode }) => {
    const env = loadEnv(mode, process.cwd(), '')
    return {
        plugins: [vue(),tailwindcss(),
            {
                name: 'serve-external-storage',
                configureServer(server) {
                    const baseDir = path.resolve(__dirname, '../storage')
                    server.middlewares.use('/storage', (req, res, next) => {
                        const rel = decodeURIComponent(req.url.replace(/^\/storage/, '') || '/')
                        const filePath = path.join(baseDir, rel)
                        fs.stat(filePath, (err, stat) => {
                            if (err || !stat.isFile()) return next()
                            res.setHeader('Cache-Control', 'no-cache')
                            fs.createReadStream(filePath).pipe(res)
                        })
                    })
                }
            }
        ],
        server: {
            port: 5173,
            strictPort: true,
            proxy: {
                '/api': {
                    target: env.VITE_API_ORIGIN || 'http://localhost:8000',
                    changeOrigin: true
                },
                '/storage': {
                    target:'http://localhost/', // same API origin
                    changeOrigin: true,
                },
            }
        },
        build: { outDir: 'dist', emptyOutDir: true }
    }
})