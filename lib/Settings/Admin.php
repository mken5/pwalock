<?php
declare(strict_types=1);

namespace OCA\PwaLock\Settings;

use OCP\AppFramework\Http\TemplateResponse;
use OCP\IConfig;
use OCP\Settings\ISettings;

final class Admin implements ISettings {
	private const APP_ID = 'pwalock';

	public function __construct(private IConfig $config) {}

	public function getForm(): TemplateResponse {
		$encryptionMode = $this->config->getAppValue(self::APP_ID, 'encryptionMode', 'server');
		if (!in_array($encryptionMode, ['server', 'local'], true)) {
			$encryptionMode = 'server';
		}

		$keyMethod = $this->config->getAppValue(self::APP_ID, 'keyMethod', 'pbkdf2');
		if (!in_array($keyMethod, ['pbkdf2', 'sha256'], true)) {
			$keyMethod = 'pbkdf2';
		}

		$askOnBackground = $this->config->getAppValue(self::APP_ID, 'askOnBackground', '1');
		$defaultIdleSeconds = (int)$this->config->getAppValue(self::APP_ID, 'defaultIdleSeconds', '300');
		$maxFailures = (int)$this->config->getAppValue(self::APP_ID, 'maxFailures', '5');

		return new TemplateResponse(self::APP_ID, 'admin', [
			'encryptionMode' => $encryptionMode,
			'keyMethod' => $keyMethod,
			'askOnBackground' => $askOnBackground,
			'defaultIdleSeconds' => (string)max(5, min(86400, $defaultIdleSeconds)),
			'maxFailures' => (string)max(1, min(50, $maxFailures)),
		]);
	}

	public function getSection(): string {
		return 'pwalock';
	}

	public function getPriority(): int {
		return 50;
	}
}
