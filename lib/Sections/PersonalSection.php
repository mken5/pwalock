<?php
declare(strict_types=1);

namespace OCA\PwaLock\Sections;

use OCP\IL10N;
use OCP\IURLGenerator;
use OCP\Settings\IIconSection;

final class PersonalSection implements IIconSection {
	public function __construct(
		private IL10N $l,
		private IURLGenerator $urlGenerator,
	) {}

	public function getID(): string {
		return 'pwalock';
	}

	public function getName(): string {
		return $this->l->t('PWA Lock');
	}

	public function getPriority(): int {
		return 90;
	}

	public function getIcon(): string {
		return $this->urlGenerator->imagePath('pwalock', 'lock.svg');
	}
}
