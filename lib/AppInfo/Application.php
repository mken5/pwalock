<?php
declare(strict_types=1);

namespace OCA\PwaLock\AppInfo;

use OCA\PwaLock\Notification\Notifier;
use OCP\AppFramework\App;
use OCP\AppFramework\Bootstrap\IBootContext;
use OCP\AppFramework\Bootstrap\IBootstrap;
use OCP\AppFramework\Bootstrap\IRegistrationContext;
use OCP\Notification\IManager as INotificationManager;

final class Application extends App implements IBootstrap {

	public const APP_ID = 'pwalock';

	public function __construct(array $urlParams = []) {
		parent::__construct(self::APP_ID, $urlParams);
	}

	public function register(IRegistrationContext $context): void {
		
	}

	public function boot(\OCP\AppFramework\Bootstrap\IBootContext $context): void {
		// Register notification renderer
		/** @var \OCP\Notification\IManager $manager */
		$manager = $context->getAppContainer()->query(\OCP\Notification\IManager::class);
		$manager->registerNotifierService(\OCA\PwaLock\Notification\Notifier::class);
	
		\OCP\Util::addScript(self::APP_ID, 'overlay');
		\OCP\Util::addStyle(self::APP_ID, 'overlay');
	}

}
