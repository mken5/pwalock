<?php
/** @var array $_ */
$nonce = \OC::$server->getContentSecurityPolicyNonceManager()->getNonce();

$encryptionMode = isset($_['encryptionMode']) ? (string)$_['encryptionMode'] : 'server';
$keyMethod = isset($_['keyMethod']) ? (string)$_['keyMethod'] : 'pbkdf2';
$idleSeconds = isset($_['idleSeconds']) ? (string)$_['idleSeconds'] : '300';
$hasServerPin = isset($_['hasServerPin']) ? (string)$_['hasServerPin'] : '0';

// Hard-load the settings JS (do not rely on Application boot events)
\OCP\Util::addScript('pwalock', 'settings-personal');
?>

<div class="section">
	<h2><?php p('PWA Lock'); ?></h2>

	<p class="settings-hint">
		<?php p('Configure your personal lock timeout and unlock code for PWA usage.'); ?>
	</p>

	<noscript>
		<p class="settings-hint"><?php p('JavaScript is required to save these settings.'); ?></p>
	</noscript>

	<form id="pwalock-personal-form"
	      class="pwalock-form"
	      method="post"
	      action="javascript:void(0)"
	      data-mode="<?php p($encryptionMode); ?>"
	      data-keymethod="<?php p($keyMethod); ?>">

		<p>
			<label for="pwalock-idle">
				<strong><?php p('Idle timeout'); ?></strong>
				<span class="settings-hint"><?php p('(seconds)'); ?></span>
			</label><br>
			<input
				type="number"
				class="text"
				id="pwalock-idle"
				name="idleSeconds"
				min="5"
				max="86400"
				value="<?php p($idleSeconds); ?>"
			>
		</p>

		<?php if ($encryptionMode === 'server') { ?>
			<p>
				<label for="pwalock-pin"><strong><?php p($hasServerPin === '1' ? 'Change code' : 'Set code'); ?></strong></label><br>
				<input
					type="password"
					class="text"
					id="pwalock-pin"
					name="pin"
					autocomplete="new-password"
					minlength="4"
					maxlength="64"
				>
				<br>
				<small class="settings-hint"><?php p('Stored server-side as a secure hash.'); ?></small>
			</p>
		<?php } else { ?>
			<p>
				<label for="pwalock-localpin"><strong><?php p('Set device code'); ?></strong></label><br>
				<input
					type="password"
					class="text"
					id="pwalock-localpin"
					name="localpin"
					autocomplete="new-password"
					minlength="4"
					maxlength="64"
				>
				<br>
				<small class="settings-hint"><?php p('Stored only in this browser/device. Clearing site data will remove it.'); ?></small>
			</p>
		<?php } ?>

		<p>
			<button type="submit" class="button primary" id="pwalock-save">
				<?php p('Save'); ?>
			</button>
			<span id="pwalock-saving" class="icon-loading-small hidden" aria-hidden="true"></span>
		</p>
	</form>
</div>

<!-- no inline JS here; handled by js/settings-personal.js -->
