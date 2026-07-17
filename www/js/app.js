(() => {
	'use strict';

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

	document.addEventListener('submit', (e) => {
		if (e.defaultPrevented) {
			return;
		}
		const form = e.target;
		if (form.dataset.submitting) {
			e.preventDefault();
			return;
		}
		form.dataset.submitting = '1';
		const buttons = form.querySelectorAll('button[type="submit"], input[type="submit"]');
		setTimeout(() => {
			for (const button of buttons) {
				button.disabled = true;
			}
		}, 0);
	});

	if ('serviceWorker' in navigator) {
		window.addEventListener('load', () => {
			navigator.serviceWorker.register('/sw.js').catch(() => {});
		});
	}
})();
