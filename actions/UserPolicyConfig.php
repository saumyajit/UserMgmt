<?php

namespace Modules\UserMgmt\Actions;

use CController;

class UserPolicyConfig extends CController {

	const CONFIG_FILE = __DIR__ . '/../data/policy_config.json';
	const ACTIVITY_LOG_FILE = __DIR__ . '/../data/activity_log.json';
	const DEFAULT_MIN_ACCOUNT_AGE_DAYS = 60;
	const DEFAULT_INACTIVITY_THRESHOLD_DAYS = 45;

	public function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'min_account_age_days' => 'int32|ge 0|le 3650',
			'inactivity_threshold_days' => 'int32|ge 0|le 3650',
			'approvers' => 'string'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->respondJson(false, _('Invalid threshold values.'));
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() >= USER_TYPE_SUPER_ADMIN;
	}

	private function respondJson(bool $success, string $message, array $extra = []): void {
		header('Content-Type: application/json');
		echo json_encode(array_merge([
			'success' => $success,
			'message' => $message
		], $extra));
		session_write_close();
		exit;
	}

	private static function currentActor(): string {
		return \CWebUser::$data['username'] ?? 'unknown';
	}

	private static function loadCurrentConfig(): array {
		if (!is_file(self::CONFIG_FILE)) {
			return [
				'min_account_age_days' => self::DEFAULT_MIN_ACCOUNT_AGE_DAYS,
				'inactivity_threshold_days' => self::DEFAULT_INACTIVITY_THRESHOLD_DAYS,
				'approvers' => []
			];
		}

		$raw = @file_get_contents(self::CONFIG_FILE);
		$decoded = $raw !== false ? json_decode($raw, true) : null;

		return is_array($decoded) ? $decoded : [
			'min_account_age_days' => self::DEFAULT_MIN_ACCOUNT_AGE_DAYS,
			'inactivity_threshold_days' => self::DEFAULT_INACTIVITY_THRESHOLD_DAYS,
			'approvers' => []
		];
	}

	/**
	 * Duplicated intentionally from UserPolicyExecute::logActivity (see the
	 * comment on UserPolicy::loadConfig for why) — appends one entry to the
	 * same activity_log.json so Settings changes show up in the Audit Log
	 * alongside flag/approve/reject/disable.
	 */
	private static function logActivity(string $comment, string $actor): void {
		$file = self::ACTIVITY_LOG_FILE;
		$log = [];

		if (is_file($file)) {
			$raw = @file_get_contents($file);
			$decoded = $raw !== false ? json_decode($raw, true) : null;
			$log = is_array($decoded) ? $decoded : [];
		}

		$log[] = [
			'action' => 'settings_update',
			'userid' => null,
			'username' => null,
			'comment' => $comment,
			'actor' => $actor,
			'actor_name' => \CWebUser::$data['name'] ?? '',
			'actor_surname' => \CWebUser::$data['surname'] ?? '',
			'clock' => time()
		];

		$dir = dirname($file);
		if (!is_dir($dir)) {
			@mkdir($dir, 0755, true);
		}

		@file_put_contents($file, json_encode($log, JSON_PRETTY_PRINT));
	}

	/**
	 * Builds a human-readable "field: old → new" summary of what changed, so
	 * the Audit Log comment is meaningful instead of just "Policy updated".
	 */
	private static function describeChanges(array $old, array $new): string {
		$parts = [];

		if ((int) ($old['min_account_age_days'] ?? null) !== (int) $new['min_account_age_days']) {
			$parts[] = sprintf(_('Min account age: %s → %s days'), $old['min_account_age_days'] ?? '-', $new['min_account_age_days']);
		}
		if ((int) ($old['inactivity_threshold_days'] ?? null) !== (int) $new['inactivity_threshold_days']) {
			$parts[] = sprintf(_('Inactivity threshold: %s → %s days'), $old['inactivity_threshold_days'] ?? '-', $new['inactivity_threshold_days']);
		}

		$old_approvers = isset($old['approvers']) && is_array($old['approvers']) ? $old['approvers'] : [];
		$new_approvers = $new['approvers'];
		sort($old_approvers);
		sort($new_approvers);

		if ($old_approvers !== $new_approvers) {
			$parts[] = sprintf(
				_('Approvers: [%s] → [%s]'),
				$old_approvers ? implode(', ', $old_approvers) : _('none'),
				$new_approvers ? implode(', ', $new_approvers) : _('none')
			);
		}

		return $parts ? implode(' | ', $parts) : _('No effective change.');
	}

	protected function doAction(): void {
		$approvers_raw = trim($this->getInput('approvers', ''));
		$approvers = $approvers_raw === '' ? [] : array_values(array_unique(array_filter(
			array_map('trim', explode(',', $approvers_raw))
		)));

		$old_config = self::loadCurrentConfig();

		$config = [
			'min_account_age_days' => $this->getInput('min_account_age_days', self::DEFAULT_MIN_ACCOUNT_AGE_DAYS),
			'inactivity_threshold_days' => $this->getInput('inactivity_threshold_days', self::DEFAULT_INACTIVITY_THRESHOLD_DAYS),
			'approvers' => $approvers
		];

		$dir = dirname(self::CONFIG_FILE);
		if (!is_dir($dir)) {
			@mkdir($dir, 0755, true);
		}

		if (@file_put_contents(self::CONFIG_FILE, json_encode($config, JSON_PRETTY_PRINT)) === false) {
			$this->respondJson(false, _('Failed to save configuration (data/ not writable).'));
		}

		self::logActivity(self::describeChanges($old_config, $config), self::currentActor());

		$this->respondJson(true, _('Policy thresholds updated.'), ['config' => $config]);
	}
}
