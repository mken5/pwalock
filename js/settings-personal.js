/**
 * PWA Lock - Personal settings handler
 *
 * Expected DOM:
 * - <form id="pwalock-personal-form" ...>
 * - <input name="idleSeconds" ...>
 * - server mode: <input name="pin" ...>
 * - local mode:  <input name="localpin" ...>
 *
 * Optional DOM hints:
 * - form[data-mode="server|local"]
 * - form[data-keymethod="pbkdf2|sha256"]
 *
 * Endpoints:
 * - POST /apps/pwalock/config/user        (idleSeconds)
 * - POST /apps/pwalock/security/pin       (server pin set/change)
 */

(function () {
	const APPID = 'pwalock';

	function $(id) { return document.getElementById(id); }

	function notify(msg) {
		if (window.OC && OC.Notification) {
			OC.Notification.showTemporary(msg);
		}
	}

	function getRequestToken() {
		if (window.OC && OC.requestToken) return OC.requestToken;

		const head = document.querySelector('head');
		if (head && head.dataset && head.dataset.requesttoken) return head.dataset.requesttoken;

		const meta = document.querySelector('meta[name="requesttoken"]');
		if (meta && meta.content) return meta.content;

		return '';
	}

	function getModeAndKeyMethod(form) {
		// Prefer data attributes (recommended)
		const mode = (form.dataset && form.dataset.mode) ? String(form.dataset.mode) : '';
		const keyMethod = (form.dataset && form.dataset.keymethod) ? String(form.dataset.keymethod) : '';

		return {
			mode: (mode === 'local' || mode === 'server') ? mode : 'server',
			keyMethod: (keyMethod === 'sha256' || keyMethod === 'pbkdf2') ? keyMethod : 'pbkdf2',
		};
	}

	function setBusy(isBusy) {
		const saving = $('pwalock-saving');
		const btn = $('pwalock-save');
		if (saving) saving.classList.toggle('hidden', !isBusy);
		if (btn) btn.disabled = !!isBusy;
	}

	// ---- Local verifier helpers (for local mode) ----
	function enc(s) { return new TextEncoder().encode(s); }

	function randomHex(nBytes = 16) {
		const a = new Uint8Array(nBytes);
		crypto.getRandomValues(a);
		return Array.from(a).map(b => b.toString(16).padStart(2, '0')).join('');
	}

	function hexToBuf(hex) {
		const a = new Uint8Array(hex.length / 2);
		for (let i = 0; i < a.length; i++) {
			a[i] = parseInt(hex.slice(i * 2, i * 2 + 2), 16);
		}
		return a.buffer;
	}

	async function sha256Hex(s) {
		const digest = await crypto.subtle.digest('SHA-256', enc(s));
		return Array.from(new Uint8Array(digest)).map(b => b.toString(16).padStart(2, '0')).join('');
	}

	async function pbkdf2Hex(password, saltHex, iterations = 120000) {
		const keyMaterial = await crypto.subtle.importKey(
			'raw',
			enc(password),
			{ name: 'PBKDF2' },
			false,
			['deriveBits']
		);
		const salt = new Uint8Array(hexToBuf(saltHex));
		const bits = await crypto.subtle.deriveBits(
			{ name: 'PBKDF2', salt, iterations, hash: 'SHA-256' },
			keyMaterial,
			256
		);
		return Array.from(new Uint8Array(bits)).map(b => b.toString(16).padStart(2, '0')).join('');
	}

	async function storeLocalVerifier(pin, keyMethod) {
		const salt = randomHex(16);
		let verifier;
		if (keyMethod === 'pbkdf2') {
			verifier = await pbkdf2Hex(pin, salt, 120000);
		} else {
			verifier = await sha256Hex(pin + ':' + salt);
		}
		localStorage.setItem('pwalock_local_salt', salt);
		localStorage.setItem('pwalock_local_verifier', verifier);
	}

	// ---- HTTP helpers ----
	async function postFormUrlEncoded(url, token, params) {
		const body = new URLSearchParams();
		for (const [k, v] of Object.entries(params)) {
			body.set(k, String(v));
		}

		const res = await fetch(url, {
			method: 'POST',
			headers: {
				'requesttoken': token,
				'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
			},
			body,
		});

		const json = await res.json().catch(() => ({}));
		return { res, json };
	}

	// ---- Main submit handler ----
	async function onSubmit(e) {
		e.preventDefault();

		const form = e.currentTarget;
		const token = getRequestToken();

		if (!token) {
			notify('Missing request token; cannot save settings.');
			return;
		}
		if (!(window.OC && OC.generateUrl)) {
			notify('Nextcloud client helpers unavailable; cannot save settings.');
			return;
		}

		const { mode, keyMethod } = getModeAndKeyMethod(form);
		const data = new FormData(form);

		const idleSeconds = String(data.get('idleSeconds') || '').trim();
		const serverPin = String(data.get('pin') || '').trim();
		const localPin = String(data.get('localpin') || '').trim();

		setBusy(true);

		try {
			// 1) Save idle timeout
			{
				const url = OC.generateUrl('/apps/' + APPID + '/config/user');
				const { res, json } = await postFormUrlEncoded(url, token, { idleSeconds: idleSeconds || '300' });

				if (!res.ok || !json || json.status !== 'ok') {
					notify('Error saving idle timeout.');
					return;
				}
			}

			// 2) Save code (optional) depending on mode
			if (mode === 'server') {
				// Only call server endpoint if user entered a PIN
				if (serverPin.length > 0) {
					if (serverPin.length < 4) {
						notify('Code too short (minimum 4).');
						return;
					}
					const url = OC.generateUrl('/apps/' + APPID + '/security/pin');
					const { res, json } = await postFormUrlEncoded(url, token, { pin: serverPin });

					if (!res.ok || !json || json.status !== 'ok') {
						// If server returns message, surface it (without exposing pin)
						const msg = (json && json.message) ? String(json.message) : 'Error saving code.';
						notify(msg);
						return;
					}
				}
			} else {
				// local mode: store verifier in browser only (optional)
				if (localPin.length > 0) {
					if (localPin.length < 4) {
						notify('Code too short (minimum 4).');
						return;
					}
					await storeLocalVerifier(localPin, keyMethod);
				}
			}

			// Clear any PIN input after success
			const pinInput = form.querySelector('input[name="pin"], input[name="localpin"]');
			if (pinInput) pinInput.value = '';

			notify('PWA Lock settings saved.');
		} catch (err) {
			notify('Error saving PWA Lock settings.');
		} finally {
			setBusy(false);
		}
	}

	function init() {
		const form = $('pwalock-personal-form');
		if (!form) return;

		// Safety: enforce no-GET fallback even if template forgot it
		// (This does not replace fixing the template.)
		if (!form.getAttribute('method')) form.setAttribute('method', 'post');
		if (!form.getAttribute('action')) form.setAttribute('action', 'javascript:void(0)');

		form.addEventListener('submit', onSubmit);
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', init);
	} else {
		init();
	}
})();
