<?php
declare(strict_types=1);

namespace OCA\PwaLock\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\IUserSession;
use OCP\Settings\ISettings;

final class Personal implements ISettings {
	private const APP_ID = 'pwalock';

	public function __construct(
		private IConfig $config,
		private IUserSession $userSession,
	) {}

	public function getForm(): TemplateResponse {
		$user = $this->userSession->getUser();
		$uid = $user ? $user->getUID() : '';

		$encryptionMode = $this->config->getAppValue(self::APP_ID, 'encryptionMode', 'server');
		if (!in_array($encryptionMode, ['server', 'local'], true)) {
			$encryptionMode = 'server';
		}

		$keyMethod = $this->config->getAppValue(self::APP_ID, 'keyMethod', 'pbkdf2');
		if (!in_array($keyMethod, ['pbkdf2', 'sha256'], true)) {
			$keyMethod = 'pbkdf2';
		}

		$defaultIdleSeconds = (int)$this->config->getAppValue(self::APP_ID, 'defaultIdleSeconds', '300');
		$defaultIdleSeconds = max(5, min(86400, $defaultIdleSeconds));

		$idleSeconds = $defaultIdleSeconds;
		$hasServerPin = '0';

		if ($uid !== '') {
			$idleSeconds = (int)$this->config->getUserValue($uid, self::APP_ID, 'idleSeconds', (string)$defaultIdleSeconds);
			$idleSeconds = max(5, min(86400, $idleSeconds));

			$pinHash = $this->config->getUserValue($uid, self::APP_ID, 'pinHash', '');
			$hasServerPin = ($pinHash !== '') ? '1' : '0';
		}

		return new TemplateResponse(self::APP_ID, 'personal', [
			'encryptionMode' => $encryptionMode,
			'keyMethod' => $keyMethod,
			'idleSeconds' => (string)$idleSeconds,
			'hasServerPin' => $hasServerPin,
		]);
	}

	public function getSection(): string {
		return 'pwalock';
	}

	public function getPriority(): int {
		return 50;
	}
}
