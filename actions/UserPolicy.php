<?php

namespace Modules\UserMgmt\Actions;

use API;
use CController;
use CControllerResponseData;

class UserPolicy extends CController {

	const DEFAULT_MIN_ACCOUNT_AGE_DAYS = 60;
	const DEFAULT_INACTIVITY_THRESHOLD_DAYS = 45;
	const CONFIG_FILE = __DIR__ . '/../data/policy_config.json';

	public function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		return true;
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() >= USER_TYPE_SUPER_ADMIN;
	}

	/**
	 * Duplicated intentionally in UserPolicyExecute.php and UserPolicyConfig.php
	 * so no cross-file class/namespace dependency exists outside of registered
	 * module actions (see memory: avoids autoload/redeclare fatals seen previously).
	 */
	private static function loadConfig(): array {
		$defaults = [
			'min_account_age_days' => self::DEFAULT_MIN_ACCOUNT_AGE_DAYS,
			'inactivity_threshold_days' => self::DEFAULT_INACTIVITY_THRESHOLD_DAYS
		];

		if (!is_file(self::CONFIG_FILE)) {
			return $defaults;
		}

		$raw = @file_get_contents(self::CONFIG_FILE);
		$decoded = $raw !== false ? json_decode($raw, true) : null;

		if (!is_array($decoded)) {
			return $defaults;
		}

		return [
			'min_account_age_days' => isset($decoded['min_account_age_days'])
				? (int) $decoded['min_account_age_days']
				: $defaults['min_account_age_days'],
			'inactivity_threshold_days' => isset($decoded['inactivity_threshold_days'])
				? (int) $decoded['inactivity_threshold_days']
				: $defaults['inactivity_threshold_days']
		];
	}

	private static function loadApprovalQueue(): array {
		$file = __DIR__ . '/../data/approval_queue.json';

		if (!is_file($file)) {
			return [];
		}

		$raw = @file_get_contents($file);
		$decoded = $raw !== false ? json_decode($raw, true) : null;

		return is_array($decoded) ? $decoded : [];
	}

	protected function doAction(): void {
		$config = self::loadConfig();
		$now = time();

		/*
		 * ------------------------------------------------------------
		 * 1. Get all Zabbix users
		 * ------------------------------------------------------------
		 */
		$users = API::User()->get([
			'output' => [
				'userid',
				'username',
				'name',
				'surname',
				'roleid'
			],
			'sortfield' => 'username',
			'sortorder' => ZBX_SORT_UP
		]);

		/*
		 * ------------------------------------------------------------
		 * 2. Get user creation audit records
		 *
		 * action = 0 -> Add, resourcetype = 0 -> User
		 * ------------------------------------------------------------
		 */
		$user_creation_logs = API::AuditLog()->get([
			'output' => ['auditid', 'userid', 'username', 'clock', 'action', 'resourcetype', 'resourceid', 'resourcename', 'ip'],
			'filter' => ['action' => 0, 'resourcetype' => 0],
			'sortfield' => 'clock',
			'sortorder' => ZBX_SORT_DOWN
		]);

		$creation_times = [];
		foreach ($user_creation_logs as $audit) {
			$userid = (string) $audit['resourceid'];
			if (!isset($creation_times[$userid])) {
				$creation_times[$userid] = (int) $audit['clock'];
			}
		}

		/*
		 * ------------------------------------------------------------
		 * 3. Get login audit records
		 *
		 * action = 8 -> Login, resourcetype = 0 -> User
		 * ------------------------------------------------------------
		 */
		$login_logs = API::AuditLog()->get([
			'output' => ['auditid', 'userid', 'username', 'clock', 'action', 'resourcetype', 'resourceid', 'resourcename', 'ip'],
			'filter' => ['action' => 8, 'resourcetype' => 0],
			'sortfield' => 'clock',
			'sortorder' => ZBX_SORT_DOWN,
			'limit' => 2000
		]);

		$last_login_times = [];
		foreach ($login_logs as $login) {
			$userid = (string) $login['userid'];
			if (!isset($last_login_times[$userid])) {
				$last_login_times[$userid] = (int) $login['clock'];
			}
		}

		$approval_queue = self::loadApprovalQueue();
		$pending_userids = [];
		foreach ($approval_queue as $entry) {
			if (($entry['status'] ?? '') === 'pending') {
				$pending_userids[(string) $entry['userid']] = $entry;
			}
		}

		/*
		 * ------------------------------------------------------------
		 * 4. Compute policy fields per user
		 *
		 *   creation_age > min_account_age_days
		 *     AND last_login is NULL                        => DISABLE (never_logged_in)
		 *   creation_age > min_account_age_days
		 *     AND last_login exists
		 *     AND last_login_age > inactivity_threshold_days => DISABLE (inactive)
		 *   Everything else                                  => NO ACTION
		 * ------------------------------------------------------------
		 */
		foreach ($users as &$user) {
			$userid = (string) $user['userid'];

			$creation_clock = $creation_times[$userid] ?? null;
			$last_login_clock = $last_login_times[$userid] ?? null;

			$creation_age_days = $creation_clock !== null
				? (int) floor(($now - $creation_clock) / 86400)
				: null;

			$last_login_age_days = $last_login_clock !== null
				? (int) floor(($now - $last_login_clock) / 86400)
				: null;

			$account_old_enough = $creation_age_days !== null
				&& $creation_age_days > $config['min_account_age_days'];

			$never_logged_in = $last_login_clock === null;
			$inactive_past_threshold = $last_login_age_days !== null
				&& $last_login_age_days > $config['inactivity_threshold_days'];

			if ($account_old_enough && $never_logged_in) {
				$recommendation = 'disable';
				$reason = 'never_logged_in';
			}
			elseif ($account_old_enough && $inactive_past_threshold) {
				$recommendation = 'disable';
				$reason = 'inactive';
			}
			elseif (!$account_old_enough && $creation_age_days !== null) {
				$recommendation = 'no_action';
				$reason = 'new_account';
			}
			else {
				$recommendation = 'no_action';
				$reason = $never_logged_in ? 'unknown_creation' : 'active';
			}

			$user['creation_clock'] = $creation_clock;
			$user['creation_age_days'] = $creation_age_days;
			$user['last_login_clock'] = $last_login_clock;
			$user['last_login_age_days'] = $last_login_age_days;
			$user['recommendation'] = $recommendation;
			$user['reason'] = $reason;
			$user['pending_approval'] = isset($pending_userids[$userid]);
			$user['pending_comment'] = $pending_userids[$userid]['comment'] ?? null;
		}
		unset($user);

		$summary = [
			'total' => count($users),
			'never_logged_in' => 0,
			'inactive_over_threshold' => 0,
			'recommended_disable' => 0
		];

		foreach ($users as $user) {
			if ($user['reason'] === 'never_logged_in') {
				$summary['never_logged_in']++;
			}
			if ($user['reason'] === 'inactive') {
				$summary['inactive_over_threshold']++;
			}
			if ($user['recommendation'] === 'disable') {
				$summary['recommended_disable']++;
			}
		}

		$data = [
			'title' => _('User Management'),
			'users' => $users,
			'login_logs' => array_slice($login_logs, 0, 50),
			'config' => $config,
			'summary' => $summary
		];

		$this->setResponse(new CControllerResponseData($data));
	}
}
