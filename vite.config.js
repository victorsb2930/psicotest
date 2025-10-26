import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { nodePolyfills } from 'vite-plugin-node-polyfills'

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
				'resources/css/pages/messages.css'
			],
			refresh: true,
		}),
		nodePolyfills(),
	],
	define: {
		global: {},
	},
	// Drop all console.* and debugger in production builds to keep console clean
	esbuild: mode === 'production' ? { drop: ['console', 'debugger'] } : {},
}));