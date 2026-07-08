/*
 * Kamto — jediný klientský skript (žádná knihovna). Externalizováno z inline <script> kvůli CSP
 * `script-src 'self'` (žádný inline JS v HTML). Načítá se přes <script src="/js/app.js" defer>
 * v @layout.latte, takže DOM je při běhu hotové.
 *
 * Obsah: (1) nativní <dialog> úpravy částky, (2) anti-dvojklik na formulářích, (3) registrace
 * service workeru.
 */
(() => {
	'use strict';

	// --- 1. Nativní <dialog> (úprava částky na dashboardu) --------------------------------------
	// Esc a focus-trap řeší prohlížeč sám; my jen otevíráme/zavíráme a znovuotevřeme dialog,
	// který se po chybě validace vrátil se serveru (data-reopen).
	document.addEventListener('click', (e) => {
		const opener = e.target.closest('[data-dialog-open]');
		if (opener) {
			document.getElementById(opener.dataset.dialogOpen)?.showModal();
			return;
		}
		if (e.target.closest('[data-dialog-close]')) {
			e.target.closest('dialog')?.close();
		}
	});

	for (const dialog of document.querySelectorAll('dialog[data-reopen]')) {
		dialog.showModal();
	}

	// --- 2. Anti-dvojklik ------------------------------------------------------------------------
	// Po prvním odeslání blokuj další odeslání téhož formuláře (duplicitní služba/kategorie/platba).
	// Server je i tak idempotentní — tohle je čistě UX pojistka.
	document.addEventListener('submit', (e) => {
		if (e.defaultPrevented) {
			return; // jiný listener (např. validace) submit zrušil — neblokuj budoucí pokus
		}
		const form = e.target;
		if (form.dataset.submitting) {
			e.preventDefault(); // druhé odeslání během probíhajícího prvního
			return;
		}
		form.dataset.submitting = '1';
		// Vizuální disable AŽ po serializaci tohoto odeslání (setTimeout 0), jinak by se name/value
		// stisknutého tlačítka nedostalo na server (Nette podle něj pozná, které tlačítko bylo).
		const buttons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
		setTimeout(() => {
			for (const button of buttons) {
				button.disabled = true;
			}
		}, 0);
	});

	// --- 3. Service worker (offline app shell) ---------------------------------------------------
	if ('serviceWorker' in navigator) {
		window.addEventListener('load', () => {
			navigator.serviceWorker.register('/sw.js').catch(() => {
				/* SW je progresivní vylepšení — když se nezaregistruje, appka běží dál. */
			});
		});
	}
})();
