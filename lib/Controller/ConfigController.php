<?php
declare(strict_types=1);

namespace OCA\PwaLock\Controller;

use OCP\AppFramework\Controller;
use OCP\AppFramework\Http\DataResponse;
use OCP\IConfig;
use OCP\IRequest;
use OCP\IUserSession;

final class ConfigController extends Controller {
	private const APP_ID = 'pwalock';

	public function __construct(
		string $appName,
		IRequest $request,
		private IConfig $config,
		private IUserSession $userSession,
	) {
		parent::__construct($appName, $request);
	}

	/**
	 * Returns merged configuration for the current user.
	 *
	 * Used by the PWA client (overlay.js).
	 *
	 * @NoAdminRequired
         * @NoCSRFRequired
	 */
	public function effective(): DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new DataResponse(['status' => 'error', 'message' => 'Not logged in'], 401);
		}
		$uid = $user->getUID();

		$encryptionMode = $this->config->getAppValue(self::APP_ID, 'encryptionMode', 'server');
		if (!in_array($encryptionMode, ['server', 'local'], true)) {
			$encryptionMode = 'server';
		}

		$keyMethod = $this->config->getAppValue(self::APP_ID, 'keyMethod', 'pbkdf2');
		if (!in_array($keyMethod, ['pbkdf2', 'sha256'], true)) {
			$keyMethod = 'pbkdf2';
		}

		$askOnBackground = $this->config->getAppValue(self::APP_ID, 'askOnBackground', '1') === '1';

		$defaultIdleSeconds = (int)$this->config->getAppValue(self::APP_ID, 'defaultIdleSeconds', '300');
		$defaultIdleSeconds = max(5, min(86400, $defaultIdleSeconds));

		$maxFailures = (int)$this->config->getAppValue(self::APP_ID, 'maxFailures', '5');
		$maxFailures = max(1, min(50, $maxFailures));

		$idleSeconds = (int)$this->config->getUserValue($uid, self::APP_ID, 'idleSeconds', (string)$defaultIdleSeconds);
		$idleSeconds = max(5, min(86400, $idleSeconds));

		$pinHash = $this->config->getUserValue($uid, self::APP_ID, 'pinHash', '');
		$hasServerPin = $pinHash !== '';

		return new DataResponse([
			'status' => 'ok',
			'encryptionMode' => $encryptionMode,
			'keyMethod' => $keyMethod,
			'askOnBackground' => $askOnBackground,
			'defaultIdleSeconds' => $defaultIdleSeconds,
			'idleSeconds' => $idleSeconds,
			'maxFailures' => $maxFailures,
			'hasServerPin' => $hasServerPin,
		]);
	}

	/**
	 * Admin settings save.
	 *
	 * @AdminRequired
	 * @CSRFRequired
	 */
	public function saveAdmin(
		string $encryptionMode = 'server',
		string $keyMethod = 'pbkdf2',
		string $askOnBackground = '1',
		int $defaultIdleSeconds = 300,
		int $maxFailures = 5,
	): DataResponse {
		$encryptionMode = in_array($encryptionMode, ['server', 'local'], true) ? $encryptionMode : 'server';
		$keyMethod = in_array($keyMethod, ['pbkdf2', 'sha256'], true) ? $keyMethod : 'pbkdf2';

		$ask = ($askOnBackground === '1' || $askOnBackground === 'true');

		$defaultIdleSeconds = max(5, min(86400, (int)$defaultIdleSeconds));
		$maxFailures = max(1, min(50, (int)$maxFailures));

		$this->config->setAppValue(self::APP_ID, 'encryptionMode', $encryptionMode);
		$this->config->setAppValue(self::APP_ID, 'keyMethod', $keyMethod);
		$this->config->setAppValue(self::APP_ID, 'askOnBackground', $ask ? '1' : '0');
		$this->config->setAppValue(self::APP_ID, 'defaultIdleSeconds', (string)$defaultIdleSeconds);
		$this->config->setAppValue(self::APP_ID, 'maxFailures', (string)$maxFailures);

		return new DataResponse(['status' => 'ok']);
	}

	/**
	 * User settings save (idle timeout only).
	 *
	 * @NoAdminRequired
	 * @CSRFRequired
	 */
	public function saveUser(int $idleSeconds = 300): DataResponse {
		$user = $this->userSession->getUser();
		if ($user === null) {
			return new DataResponse(['status' => 'error', 'message' => 'Not logged in'], 401);
		}
		$uid = $user->getUID();

		$defaultIdleSeconds = (int)$this->config->getAppValue(self::APP_ID, 'defaultIdleSeconds', '300');
		$defaultIdleSeconds = max(5, min(86400, $defaultIdleSeconds));

		$idleSeconds = max(5, min(86400, (int)$idleSeconds));
		// If user submits 0/empty, fall back to default.
		if ($idleSeconds <= 0) {
			$idleSeconds = $defaultIdleSeconds;
		}

		$this->config->setUserValue($uid, self::APP_ID, 'idleSeconds', (string)$idleSeconds);

		return new DataResponse(['status' => 'ok']);
	}

      /**
       * Backward compatible endpoint for older routes: config#get
       *
       * @NoAdminRequired
       * @NoCSRFRequired
       */
      public function get(): DataResponse {
        	return $this->effective();
      }
}
