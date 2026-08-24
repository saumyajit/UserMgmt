<?php

namespace Modules\UserMgmt\Actions;

use CController;

class UserPolicyConfig extends CController {

	const CONFIG_FILE = __DIR__ . '/../data/policy_config.json';
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

	protected function doAction(): void {
		$approvers_raw = trim($this->getInput('approvers', ''));
		$approvers = $approvers_raw === '' ? [] : array_values(array_unique(array_filter(
			array_map('trim', explode(',', $approvers_raw))
		)));

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

		$this->respondJson(true, _('Policy thresholds updated.'), ['config' => $config]);
	}
}
