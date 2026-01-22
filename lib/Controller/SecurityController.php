<?php
declare(strict_types=1);

namespace OCA\PwaLock\Controller;

use DateTime;
use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUserSession;
use OCP\Notification\IManager as INotificationManager;

final class SecurityController extends Controller {
	private const APP_ID = 'pwalock';

	public function __construct(
		string $appName,
		IRequest $request,
		private IConfig $config,
		private IUserSession $userSession,
		private INotificationManager $notifications,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Set/change server PIN (server mode).
	 *
	 * @NoAdminRequired
	 * @CSRFRequired
	 */
	public function setPin(): DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new DataResponse(['status' => 'error', 'message' => 'Not logged in'], 401);
		}
		$uid = $user->getUID();

		$pin = trim((string)$this->request->getParam('pin', ''));
		if (strlen($pin) < 4) {
			return new DataResponse(['status' => 'error', 'message' => 'PIN too short'], 400);
		}
		if (strlen($pin) > 64) {
			return new DataResponse(['status' => 'error', 'message' => 'PIN too long'], 400);
		}

		$hash = password_hash($pin, PASSWORD_DEFAULT);
		if ($hash === false) {
			return new DataResponse(['status' => 'error', 'message' => 'Hashing failed'], 500);
		}

		$this->config->setUserValue($uid, self::APP_ID, 'pinHash', $hash);
		$this->config->setUserValue($uid, self::APP_ID, 'failedAttempts', '0');

		return new DataResponse(['status' => 'ok']);
	}

	/**
	 * Verify server PIN.
	 *
	 * @NoAdminRequired
	 * @CSRFRequired
	 */
	public function verify(): DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new DataResponse(['status' => 'error', 'message' => 'Not logged in'], 401);
		}
		$uid = $user->getUID();

		$pin = trim((string)$this->request->getParam('pin', ''));
		if ($pin === '') {
			return new DataResponse(['status' => 'error', 'message' => 'Missing PIN'], 400);
		}

		$hash = $this->config->getUserValue($uid, self::APP_ID, 'pinHash', '');
		if ($hash === '') {
			return new DataResponse(['status' => 'not_configured'], 409);
		}

		$ok = password_verify($pin, $hash);

		$maxFailures = (int)$this->config->getAppValue(self::APP_ID, 'maxFailures', '5');
		$maxFailures = max(1, min(50, $maxFailures));

		$attempts = (int)$this->config->getUserValue($uid, self::APP_ID, 'failedAttempts', '0');

		if ($ok) {
			$this->config->setUserValue($uid, self::APP_ID, 'failedAttempts', '0');
			return new DataResponse(['status' => 'ok']);
		}

		$attempts++;
		$this->config->setUserValue($uid, self::APP_ID, 'failedAttempts', (string)$attempts);

		$remaining = max(0, $maxFailures - $attempts);

		if ($attempts >= $maxFailures) {
			$this->sendFailureNotification($uid, $attempts);
			$this->userSession->logout();
			return new DataResponse(['status' => 'lockedOut', 'remaining' => 0], 401);
		}

		return new DataResponse(['status' => 'fail', 'remaining' => $remaining], 403);
	}

	/**
	 * Local mode best-effort failure report.
	 *
	 * @NoAdminRequired
	 * @CSRFRequired
	 */
	public function reportFailure(): DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new DataResponse(['status' => 'error', 'message' => 'Not logged in'], 401);
		}
		$uid = $user->getUID();

		$count = (int)$this->request->getParam('count', 0);
		$count = max(0, min(500, $count));

		$maxFailures = (int)$this->config->getAppValue(self::APP_ID, 'maxFailures', '5');
		$maxFailures = max(1, min(50, $maxFailures));

		$this->config->setUserValue($uid, self::APP_ID, 'failedAttempts', (string)$count);

		if ($count >= $maxFailures) {
			$this->sendFailureNotification($uid, $count);
			$this->userSession->logout();
			return new DataResponse(['status' => 'lockedOut'], 401);
		}

		return new DataResponse(['status' => 'ok']);
	}

	/**
	 * @NoAdminRequired
	 * @CSRFRequired
	 */
	public function lockdown(): DataResponse {
		$this->userSession->logout();
		return new DataResponse(['status' => 'lockedOut'], 401);
	}

	private function sendFailureNotification(string $uid, int $count): void {
		try {
			$n = $this->notifications->createNotification();
			$n->setApp(self::APP_ID)
				->setUser($uid)
				->setDateTime(new DateTime())
				->setSubject('pwalock_failed', ['attempts' => (string)$count]);
			$this->notifications->notify($n);
		} catch (\Throwable $e) {
			// best-effort
		}
	}
}
