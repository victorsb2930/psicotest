import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { nodePolyfills } from 'vite-plugin-node-polyfills';
// Opcional: visualizador del bundle (requiere instalar rollup-plugin-visualizer)
// import { visualizer } from 'rollup-plugin-visualizer';

export default defineConfig(({ mode }) => ({
	plugins: [
		laravel({
			input: [
				'resources/css/app.css',
				'resources/js/app.js',
				// CSS PAGES
				'resources/css/loginRegister.css',
				'resources/css/layout.css',
				'resources/css/admin.css',
				'resources/css/pages/chat.css'
			],
			refresh: true,
		}),
		nodePolyfills(),
        // (Descomentar tras instalar) Sólo en producción para analizar peso
        // ...(mode === 'production' ? [visualizer({ filename: 'stats.html', template: 'treemap', gzipSize: true, brotliSize: true })] : []),
	],
	define: {
		global: {},
	},
	server: {
		host: '0.0.0.0',
		port: 5173,
	},
	// Drop all console.* and debugger in production builds to keep console clean
	esbuild: mode === 'production' ? { drop: ['console', 'debugger'] } : {},
	build: {
		chunkSizeWarningLimit: 700, // Aumenta límite tras aplicar splitting
		rollupOptions: {
			output: {
				manualChunks: {
					// Dependencias centrales agrupadas
					vendor: [
						'axios'
					],
					// Librerías de gráficos (ajustar cuando se añadan Chart.js u otras)
					chart: [
						// 'chart.js' // descomentar cuando se use
					],
					// RTC / tiempo real (pusher, twilio, etc.) si se usan en ./rtc
					rtc: [
						// 'pusher-js', 'twilio-client'
					],
					// Utilidades grandes factibles de separar (lodash, dayjs, etc.)
					utils: [
						// 'lodash-es'
					]
				},
			},
		},
	},
}));
