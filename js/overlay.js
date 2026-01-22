(function () {
	const APPID = 'pwalock';

	// Optional translation helper
	const t = (typeof window.t === 'function') ? window.t : null;


	function isPWA() {
		const mq = window.matchMedia('(display-mode: standalone), (display-mode: fullscreen), (display-mode: minimal-ui)');
		const iOSStandalone = navigator.standalone === true;
		return mq.matches || iOSStandalone;
	}

	function showToast(message) {
		if (window.OC && OC.Notification) {
			OC.Notification.showTemporary(message);
		}
	}

	function setUnlockedCookie() {
		const parts = ['__Host-ncPwaUnlocked=1', 'Path=/', 'SameSite=Lax'];
		if (location.protocol === 'https:') parts.push('Secure');
		document.cookie = parts.join('; ');
	}

	function clearUnlockedCookie() {
		const parts = ['__Host-ncPwaUnlocked=', 'Path=/', 'Max-Age=0', 'SameSite=Lax'];
		if (location.protocol === 'https:') parts.push('Secure');
		document.cookie = parts.join('; ');
	}

	function hasUnlockedCookie() {
		return document.cookie.split(';').some(p => p.trim().startsWith('__Host-ncPwaUnlocked=1'));
	}

	function nowMs() { return Date.now(); }

	// Cross-window sync (best-effort)
	let bc = null;
	try { bc = ('BroadcastChannel' in window) ? new BroadcastChannel('pwalock') : null; } catch (e) { bc = null; }
	function broadcast(type, payload = {}) {
		if (!bc) return;
		try { bc.postMessage({ type, payload }); } catch (e) {}
	}

	// ---------------- Overlay UI ----------------
	let overlayEl = null;
	let locked = false;

	function ensureOverlay() {
		if (overlayEl) return;

		overlayEl = document.createElement('div');
		overlayEl.id = 'pwalock-overlay';
		overlayEl.innerHTML = `
			<div class="pwalock-card" role="dialog" aria-modal="true" aria-labelledby="pwalock-title">
				<div class="pwalock-header">
					<span class="icon icon-password" aria-hidden="true"></span>
					<h2 id="pwalock-title">${t ? t(APPID, 'Locked') : 'Locked'}</h2>
				</div>
				<p class="pwalock-sub">${t ? t(APPID, 'Enter your code to continue.') : 'Enter your code to continue.'}</p>
				<div class="pwalock-field">
					<input id="pwalock-key" class="text" type="password" inputmode="numeric" autocomplete="current-password" />
				</div>
				<div id="pwalock-err" class="pwalock-err" aria-live="polite"></div>
				<div class="pwalock-actions">
					<button id="pwalock-unlock" class="button primary">${t ? t(APPID, 'Unlock') : 'Unlock'}</button>
				</div>
				<p id="pwalock-hint" class="pwalock-hint"></p>
			</div>`;
		overlayEl.style.display = 'none';
		document.body.appendChild(overlayEl);

		const btn = overlayEl.querySelector('#pwalock-unlock');
		const input = overlayEl.querySelector('#pwalock-key');

		btn.addEventListener('click', () => tryUnlock());
		input.addEventListener('keydown', (e) => {
			if (e.key === 'Enter') tryUnlock();
		});
	}

	function setError(msg) {
		ensureOverlay();
		overlayEl.querySelector('#pwalock-err').textContent = msg || '';
	}

	function setHint(msg) {
		ensureOverlay();
		overlayEl.querySelector('#pwalock-hint').textContent = msg || '';
	}

	function showOverlay() {
		ensureOverlay();
		overlayEl.style.display = 'flex';
		document.documentElement.classList.add('pwalock-locked');
		locked = true;
		clearUnlockedCookie();
		broadcast('locked');
		const input = overlayEl.querySelector('#pwalock-key');
		setTimeout(() => input && input.focus(), 0);
	}

	function hideOverlay() {
		if (!overlayEl) return;
		overlayEl.style.display = 'none';
		document.documentElement.classList.remove('pwalock-locked');
		locked = false;
		setUnlockedCookie();
		broadcast('unlocked');
	}

	// ---------------- Config & gating ----------------
	let cfg = null;
	let failureCount = 0;
	let lastActivity = nowMs();
	let idleTimer = null;

	function localCodeExists() {
		try {
			return !!(localStorage.getItem('pwalock_local_salt') && localStorage.getItem('pwalock_local_verifier'));
		} catch (e) {
			return false;
		}
	}

	function serverCodeExists() {
		if (!cfg) return false;
		return !!cfg.hasServerPin;
	}

	function codeExists() {
		if (!cfg) return false;
		return (cfg.encryptionMode === 'server') ? serverCodeExists() : localCodeExists();
	}

	async function fetchConfig() {
		const fallback = {
			encryptionMode: 'server',
			keyMethod: 'pbkdf2',
			askOnBackground: true,
			maxFailures: 5,
			idleSeconds: 300,
			defaultIdleSeconds: 300,
			hasServerPin: false,
		};

		try {
			if (!(window.OC && OC.generateUrl && OC.requestToken)) return fallback;

			const candidates = [
				OC.generateUrl('/apps/' + APPID + '/config/effective'),
				OC.generateUrl('/apps/' + APPID + '/config'),
			];

			for (const url of candidates) {
				const res = await fetch(url, { headers: { 'requesttoken': OC.requestToken } });
				if (!res.ok) continue;
				const data = await res.json().catch(() => null);
				if (data && typeof data === 'object') {
					return Object.assign(fallback, data);
				}
			}

			return fallback;
		} catch (e) {
			return fallback;
		}
	}

	// ---------------- Local verify helpers ----------------
	function enc(s) { return new TextEncoder().encode(s); }

	async function sha256Hex(s) {
		const digest = await crypto.subtle.digest('SHA-256', enc(s));
		return Array.from(new Uint8Array(digest)).map(b => b.toString(16).padStart(2, '0')).join('');
	}

	function hexToBuf(hex) {
		const a = new Uint8Array(hex.length / 2);
		for (let i = 0; i < a.length; i++) a[i] = parseInt(hex.slice(i * 2, i * 2 + 2), 16);
		return a.buffer;
	}

	async function pbkdf2Hex(password, saltHex, iterations = 120000) {
		const keyMaterial = await crypto.subtle.importKey('raw', enc(password), { name: 'PBKDF2' }, false, ['deriveBits']);
		const salt = new Uint8Array(hexToBuf(saltHex));
		const bits = await crypto.subtle.deriveBits({ name: 'PBKDF2', salt, iterations, hash: 'SHA-256' }, keyMaterial, 256);
		return Array.from(new Uint8Array(bits)).map(b => b.toString(16).padStart(2, '0')).join('');
	}

	async function verifyLocal(pin) {
		const salt = localStorage.getItem('pwalock_local_salt');
		const verifier = localStorage.getItem('pwalock_local_verifier');
		if (!salt || !verifier) return false;

		if (cfg.keyMethod === 'pbkdf2') {
			const cand = await pbkdf2Hex(pin, salt, 120000);
			return cand === verifier;
		}
		const cand = await sha256Hex(pin + ':' + salt);
		return cand === verifier;
	}

	// ---------------- Server calls ----------------
	async function verifyServer(pin) {
		const url = OC.generateUrl('/apps/' + APPID + '/security/verify');
		const body = new URLSearchParams();
		body.set('pin', pin);
		const res = await fetch(url, { method: 'POST', headers: { 'requesttoken': OC.requestToken }, body });
		const j = await res.json().catch(() => ({}));
		return { ok: !!(j && j.status === 'ok'), payload: j };
	}

	async function reportLocalFailure() {
		const url = OC.generateUrl('/apps/' + APPID + '/security/failure');
		const body = new URLSearchParams();
		body.set('count', String(failureCount));
		await fetch(url, { method: 'POST', headers: { 'requesttoken': OC.requestToken }, body }).catch(() => {});
	}

	// ---------------- Unlock ----------------
	async function tryUnlock() {
		if (!cfg) return;

		// Safety: never trap user behind overlay if code is not configured.
		if (!codeExists()) {
			hideOverlay();
			showToast(
				t ? t(APPID, 'PWA Lock is not configured. Set your code in Personal settings to enable locking.') :
					'PWA Lock is not configured. Set your code in Personal settings to enable locking.'
			);
			return;
		}

		ensureOverlay();
		setError('');

		const input = overlayEl.querySelector('#pwalock-key');
		const pin = String(input.value || '').trim();
		input.value = '';

		if (pin.length < 4) {
			setError(t ? t(APPID, 'Code is too short.') : 'Code is too short.');
			return;
		}

		try {
			if (cfg.encryptionMode === 'server') {
				const r = await verifyServer(pin);
				if (r.ok) {
					failureCount = 0;
					hideOverlay();
					return;
				}

				failureCount += 1;
				const remaining = Math.max(0, Number(cfg.maxFailures || 5) - failureCount);
				setError(
					(t ? t(APPID, 'Incorrect code.') : 'Incorrect code.') + ' ' +
					(t ? t(APPID, 'Remaining attempts: {n}', { n: remaining }) : `Remaining attempts: ${remaining}`)
				);

				if (remaining <= 0) {
					showToast(t ? t(APPID, 'Too many failed attempts. You have been logged out.') : 'Too many failed attempts. You have been logged out.');
					setTimeout(() => location.reload(), 750);
				}
				return;
			}

			// local mode
			const ok = await verifyLocal(pin);
			if (ok) {
				failureCount = 0;
				hideOverlay();
				return;
			}

			failureCount += 1;
			const remaining = Math.max(0, Number(cfg.maxFailures || 5) - failureCount);
			setError(
				(t ? t(APPID, 'Incorrect code.') : 'Incorrect code.') + ' ' +
				(t ? t(APPID, 'Remaining attempts: {n}', { n: remaining }) : `Remaining attempts: ${remaining}`)
			);

			if (failureCount >= Number(cfg.maxFailures || 5)) {
				await reportLocalFailure();
				showToast(t ? t(APPID, 'Too many failed attempts. You have been logged out.') : 'Too many failed attempts. You have been logged out.');
				setTimeout(() => location.reload(), 750);
			}
		} catch (e) {
			setError(t ? t(APPID, 'Error verifying code.') : 'Error verifying code.');
		}
	}

	// ---------------- Triggers ----------------
	function markActivity() { lastActivity = nowMs(); }

	function startIdleTimer() {
		if (idleTimer) clearInterval(idleTimer);
		const idleMs = Math.max(5000, Number(cfg.idleSeconds || cfg.defaultIdleSeconds || 300) * 1000);

		idleTimer = setInterval(() => {
			if (!locked && codeExists() && (nowMs() - lastActivity) > idleMs) {
				setHint(t ? t(APPID, 'Locked due to inactivity.') : 'Locked due to inactivity.');
				showOverlay();
			}
		}, 1000);
	}

	function onVisibilityChange() {
		if (!cfg || locked) return;
		if (!codeExists()) return;

		if (cfg.askOnBackground && document.visibilityState !== 'visible') {
			setHint(t ? t(APPID, 'Locked while in background.') : 'Locked while in background.');
			showOverlay();
		}
	}

	// ---------------- Main ----------------
	async function main() {
		if (!isPWA()) return;
		if (!(window.OC && OC.generateUrl && OC.requestToken)) return;

		cfg = await fetchConfig();

		// If no code is configured, do not auto-lock; show a one-time prompt.
		if (!codeExists()) {
			try {
				if (!sessionStorage.getItem('pwalock_prompted')) {
					sessionStorage.setItem('pwalock_prompted', '1');
					showToast(
						t ? t(APPID, 'To enable PWA Lock, set your code in Personal settings.') :
							'To enable PWA Lock, set your code in Personal settings.'
					);
				}
			} catch (e) {}
			return;
		}

		// Only lock on load when code exists and we have not unlocked this instance yet.
		if (!hasUnlockedCookie()) {
			setHint('');
			showOverlay();
		}

		// Activity & triggers
		document.addEventListener('mousemove', markActivity, { passive: true });
		document.addEventListener('keydown', markActivity, { passive: true });
		document.addEventListener('touchstart', markActivity, { passive: true });
		document.addEventListener('click', markActivity, { passive: true });

		document.addEventListener('visibilitychange', onVisibilityChange);
		startIdleTimer();

		if (bc) {
			bc.onmessage = (ev) => {
				if (!ev || !ev.data) return;
				if (!codeExists()) return;
				if (ev.data.type === 'locked' && !locked) showOverlay();
				if (ev.data.type === 'unlocked' && locked) hideOverlay();
			};
		}
	}

	if (document.readyState === 'loading') {
		document.addEventListener('DOMContentLoaded', main);
	} else {
		main();
	}
})();
