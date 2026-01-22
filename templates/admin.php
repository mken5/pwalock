<?php
/** @var array $_ */
$nonce = \OC::$server->getContentSecurityPolicyNonceManager()->getNonce();

$encryptionMode = isset($_['encryptionMode']) ? (string)$_['encryptionMode'] : 'server';
$keyMethod = isset($_['keyMethod']) ? (string)$_['keyMethod'] : 'pbkdf2';
$askOnBackground = isset($_['askOnBackground']) ? (string)$_['askOnBackground'] : '1';
$defaultIdleSeconds = isset($_['defaultIdleSeconds']) ? (string)$_['defaultIdleSeconds'] : '300';
$maxFailures = isset($_['maxFailures']) ? (string)$_['maxFailures'] : '5';

// Hard-load the settings JS
\OCP\Util::addScript('pwalock', 'settings-admin');
?>

<div class="section">
	<h2><?php p('PWA Lock'); ?></h2>

	<p class="settings-hint">
		<?php p('Configure global defaults for PWA Lock.'); ?>
	</p>

	<noscript>
		<p class="settings-hint"><?php p('JavaScript is required to save these settings.'); ?></p>
	</noscript>

	<form id="pwalock-admin-form" class="pwalock-form" method="post" action="javascript:void(0)">
		<p>
			<label for="pwalock-mode"><strong><?php p('Unlock verification mode'); ?></strong></label><br>
			<select class="select" id="pwalock-mode" name="encryptionMode">
				<option value="server" <?php if ($encryptionMode === 'server') { p('selected'); } ?>><?php p('Server verified (recommended)'); ?></option>
				<option value="local" <?php if ($encryptionMode === 'local') { p('selected'); } ?>><?php p('Local only (device storage)'); ?></option>
			</select>
		</p>

		<p>
			<label for="pwalock-keyMethod"><strong><?php p('Local verification method'); ?></strong> <span class="settings-hint"><?php p('(local mode only)'); ?></span></label><br>
			<select class="select" id="pwalock-keyMethod" name="keyMethod">
				<option value="pbkdf2" <?php if ($keyMethod === 'pbkdf2') { p('selected'); } ?>><?php p('PBKDF2 (recommended)'); ?></option>
				<option value="sha256" <?php if ($keyMethod === 'sha256') { p('selected'); } ?>><?php p('SHA-256 (faster, less resistant)'); ?></option>
			</select>
		</p>

		<p>
			<label>
				<input type="checkbox" name="askOnBackground" id="pwalock-askOnBackground" value="1" <?php if ($askOnBackground === '1') { p('checked'); } ?>>
				<?php p('Lock when PWA is backgrounded (visibility hidden)'); ?>
			</label>
		</p>

		<p>
			<label for="pwalock-defaultIdle"><strong><?php p('Default idle timeout'); ?></strong> <span class="settings-hint"><?php p('(seconds)'); ?></span></label><br>
			<input type="number" class="text" id="pwalock-defaultIdle" name="defaultIdleSeconds" min="5" max="86400" value="<?php p($defaultIdleSeconds); ?>">
		</p>

		<p>
			<label for="pwalock-maxFailures"><strong><?php p('Max failed attempts'); ?></strong> <span class="settings-hint"><?php p('(before logout)'); ?></span></label><br>
			<input type="number" class="text" id="pwalock-maxFailures" name="maxFailures" min="1" max="50" value="<?php p($maxFailures); ?>">
		</p>

		<p>
			<button type="submit" class="button primary" id="pwalock-save"><?php p('Save'); ?></button>
			<span id="pwalock-saving" class="icon-loading-small hidden" aria-hidden="true"></span>
		</p>
	</form>
</div>

<!-- no inline JS here; handled by js/settings-admin.js -->
