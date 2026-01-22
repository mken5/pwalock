/**
 * PWA Lock - Admin settings handler
 *
 * Endpoints:
 * - POST /apps/pwalock/config/admin
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

	function setBusy(isBusy) {
		const saving = $('pwalock-saving');
		const btn = $('pwalock-save');
		if (saving) saving.classList.toggle('hidden', !isBusy);
		if (btn) btn.disabled = !!isBusy;
	}

	async function postForm(url, token, params) {
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

	async function onSubmit(e) {
		e.preventDefault();

		const form = e.currentTarget;
		const token = getRequestToken();

		if (!token) {
			notify('Missing request token; cannot save settings.');
			return;
		}
		if (!(window.OC && OC.generateUrl)) {
			notify('Nextcloud helpers unavailable.');
			return;
		}

		const data = new FormData(form);

		const payload = {
			encryptionMode: String(data.get('encryptionMode') || 'server'),
			keyMethod: String(data.get('keyMethod') || 'pbkdf2'),
			askOnBackground: data.get('askOnBackground') ? '1' : '0',
			defaultIdleSeconds: String(data.get('defaultIdleSeconds') || '300'),
			maxFailures: String(data.get('maxFailures') || '5'),
		};

		setBusy(true);

		try {
			const url = OC.generateUrl('/apps/' + APPID + '/config/admin');
			const { res, json } = await postForm(url, token, payload);

			if (!res.ok || !json || json.status !== 'ok') {
				notify('Error saving PWA Lock admin settings.');
				return;
			}

			notify('PWA Lock admin settings saved.');
		} catch (err) {
			notify('Error saving PWA Lock admin settings.');
		} finally {
			setBusy(false);
		}
	}

	function init() {
		const form = $('pwalock-admin-form');
		if (!form) return;

		// Hard safety: prevent GET fallback
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
