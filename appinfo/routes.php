<?php
declare(strict_types=1);

/** @var $this OCP\Route\IRouter */
return [
	'routes' => [
                // Backward-compatible alias (fixes GET /apps/pwalock/config)
                ['name' => 'config#get', 'url' => '/config', 'verb' => 'GET'],

		// Effective merged config for the PWA client
		['name' => 'config#effective', 'url' => '/config/effective', 'verb' => 'GET'],

		// Admin settings persistence
		['name' => 'config#saveAdmin', 'url' => '/config/admin', 'verb' => 'POST'],

		// User settings persistence
		['name' => 'config#saveUser', 'url' => '/config/user', 'verb' => 'POST'],

		// PIN management (server mode)
		['name' => 'security#setPin', 'url' => '/security/pin', 'verb' => 'POST'],
		['name' => 'security#verify', 'url' => '/security/verify', 'verb' => 'POST'],

		// Local mode failure reporting (best-effort)
		['name' => 'security#reportFailure', 'url' => '/security/failure', 'verb' => 'POST'],

		// Force logout
		['name' => 'security#lockdown', 'url' => '/security/lockdown', 'verb' => 'POST'],
	],
];
