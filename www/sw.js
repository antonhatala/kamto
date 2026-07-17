const CACHE_VERSION = 'kamto-static-v6';

const CORE_ASSETS = ['/css/app.css', '/js/app.js', '/manifest.json', '/offline.html'];

const ICON_ASSETS = [
	'/icons/icon.svg',
	'/icons/icon-maskable.svg',
	'/icons/favicon.png',
	'/icons/icon-192.png',
	'/icons/icon-192-maskable.png',
	'/icons/icon-512.png',
	'/icons/icon-512-maskable.png',
	'/icons/apple-touch-icon.png',
];

function freshRequest(url) {
	return new Request(url, { cache: 'reload' });
}

self.addEventListener('install', (event) => {
	event.waitUntil((async () => {
		const cache = await caches.open(CACHE_VERSION);
		await cache.addAll(CORE_ASSETS.map(freshRequest));
		await Promise.allSettled(ICON_ASSETS.map((url) => cache.add(freshRequest(url))));
		await self.skipWaiting();
	})());
});

self.addEventListener('activate', (event) => {
	event.waitUntil((async () => {
		const keys = await caches.keys();
		await Promise.all(keys.filter((key) => key !== CACHE_VERSION).map((key) => caches.delete(key)));
		await self.clients.claim();
	})());
});

self.addEventListener('fetch', (event) => {
	const request = event.request;
	const url = new URL(request.url);

	if (request.method !== 'GET' || url.origin !== self.location.origin || url.search) {
		return;
	}

	if (request.mode === 'navigate') {
		event.respondWith(fetch(request).catch(() => caches.match('/offline.html')));
		return;
	}

	if (
		url.pathname.startsWith('/css/')
		|| url.pathname.startsWith('/js/')
		|| url.pathname.startsWith('/icons/')
		|| url.pathname === '/manifest.json'
	) {
		event.respondWith((async () => {
			const cached = await caches.match(request);
			if (cached) {
				return cached;
			}
			const response = await fetch(request);
			if (response.ok) {
				const cache = await caches.open(CACHE_VERSION);
				cache.put(request, response.clone());
			}
			return response;
		})());
	}
});
