/*
 * Kamto service worker (Fáze 5) — offline app shell. Bezpečnostně citlivé: HTML se NIKDY
 * necachuje a dynamické/POST/cross-origin requesty se vůbec neintercepují, takže login/CSRF POST,
 * Set-Cookie i CSV export (?year=) procházejí nedotčené a žádné autentizované HTML neskončí v cache.
 */

// Bumpni při každé změně assetů app shellu (CSS/JS/ikony) → activate smaže starou cache.
const CACHE_VERSION = 'kamto-static-v5';

// Musí existovat teď — když jeden 404, selže instalace (záměrně, ať cache není půl prázdná).
const CORE_ASSETS = ['/css/app.css', '/js/app.js', '/manifest.json', '/offline.html'];

// Ikony best-effort: PNG dodá devops po rasterizaci SVG předloh, do té doby nesmí shodit install.
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

// {cache: 'reload'} obchází HTTP cache prohlížeče — po bumpu CACHE_VERSION se precache naplní
// čerstvými assety ze sítě, ne starou verzí z disk cache (jinak by nová SW cache mohla ožít stará).
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

	// NEintercepovat: jiné než GET, cross-origin, nebo cokoli s query stringem. Tím projdou
	// nedotčené login/CSRF POST, Set-Cookie i CSV export (/overview/export?year=…) a všechny
	// dynamické GET. Bez respondWith → default chování prohlížeče (přímo síť).
	if (request.method !== 'GET' || url.origin !== self.location.origin || url.search) {
		return;
	}

	// Navigace (HTML): network-only. HTML se NIKDY neukládá do cache. Při výpadku sítě → statická
	// offline stránka (neautentizovaná).
	if (request.mode === 'navigate') {
		event.respondWith(fetch(request).catch(() => caches.match('/offline.html')));
		return;
	}

	// Statický app shell (same-origin GET bez query): cache-first, jinak síť + doplnění cache.
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
	// Cokoli jiného (ostatní same-origin GET bez query): žádný respondWith → default síť.
});
