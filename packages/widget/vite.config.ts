import { resolve } from 'node:path'
import vue from '@vitejs/plugin-vue'
import { defineConfig } from 'vite'

export default defineConfig({
  plugins: [vue()],
  // Library builds don't set this automatically, and Vue needs it.
  define: { 'process.env.NODE_ENV': JSON.stringify('production') },
  build: {
    outDir: resolve(import.meta.dirname, '../../plugins/hitzordu/public/js'),
    emptyOutDir: true,
    lib: {
      entry: resolve(import.meta.dirname, 'src/main.ts'),
      formats: ['es'],
      fileName: () => 'hitzordu-widget.js',
    },
  },
})
