<?php
declare(strict_types=1);

namespace OCA\PwaLock\Notification;

use OCP\IL10N;
use OCP\Notification\INotification;
use OCP\Notification\INotifier;

final class Notifier implements INotifier {

	public function __construct(private IL10N $l) {}

	public function getID(): string {
		return 'pwalock';
	}

	public function getName(): string {
		return $this->l->t('PWA Lock');
	}

	public function prepare(INotification $notification, string $languageCode): INotification {
		$l = $this->l;

		if ($notification->getSubject() !== 'pwalock_failed') {
			return $notification;
		}

		$params = $notification->getSubjectParameters();
		$attempts = isset($params['attempts']) ? (int)$params['attempts'] : 0;
		$forcedLogout = isset($params['forcedLogout']) && $params['forcedLogout'] === '1';

		$title = $forcedLogout
			? $l->t('PWA Lock: too many failed unlock attempts')
			: $l->t('PWA Lock: failed unlock attempt');

		$message = $forcedLogout
			? $l->t('Unlock failed %d times. You have been logged out for safety.', [$attempts])
			: $l->t('Unlock failed %d times.', [$attempts]);

		$notification->setParsedSubject($title);
		$notification->setParsedMessage($message);

		return $notification;
	}
}
