<?php

namespace Modules\UserMgmt\Actions;

use API;
use CController;
use CControllerResponseData;

class UserPolicy extends CController {

	public function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		return true;
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() >= USER_TYPE_SUPER_ADMIN;
	}

	protected function doAction(): void {
		/*
		 * ------------------------------------------------------------
		 * Policy configuration
		 * ------------------------------------------------------------
		 */
		$account_age_threshold = 60;

		$current_time = time();

		/*
		 * ------------------------------------------------------------
		 * 1. Get enabled Zabbix users
		 *
		 * status = 0 -> Enabled
		 *
		 * We don't need disabled accounts for the current policy
		 * evaluation.
		 * ------------------------------------------------------------
		 */
		$users = API::User()->get([
			'output' => [
				'userid',
				'username',
				'name',
				'surname',
				'roleid',
				'status'
			],
			'filter' => [
				'status' => 0
			],
			'sortfield' => 'username',
			'sortorder' => ZBX_SORT_UP
		]);

		/*
		 * ------------------------------------------------------------
		 * 2. Get user creation audit records
		 *
		 * action       = 0 -> Add
		 * resourcetype = 0 -> User
		 *
		 * resourceid   = created user's userid
		 * clock        = creation timestamp
		 * ------------------------------------------------------------
		 */
		$user_creation_logs = API::AuditLog()->get([
			'output' => [
				'auditid',
				'userid',
				'username',
				'clock',
				'action',
				'resourcetype',
				'resourceid',
				'resourcename',
				'ip'
			],
			'filter' => [
				'action' => 0,
				'resourcetype' => 0
			],
			'sortfield' => 'clock',
			'sortorder' => ZBX_SORT_DESC
		]);

		/*
		 * ------------------------------------------------------------
		 * 3. Build creation-time lookup
		 *
		 *     userid => creation timestamp
		 * ------------------------------------------------------------
		 */
		$creation_times = [];

		foreach ($user_creation_logs as $audit) {
			$userid = (string) $audit['resourceid'];

			if (!isset($creation_times[$userid])) {
				$creation_times[$userid] = (int) $audit['clock'];
			}
		}

		/*
		 * ------------------------------------------------------------
		 * 4. Identify accounts older than 60 days
		 *
		 * We deliberately filter here.
		 *
		 * Users younger than 60 days do not need login-history
		 * evaluation according to the policy.
		 * ------------------------------------------------------------
		 */
		$candidate_users = [];

		foreach ($users as $user) {
			$userid = (string) $user['userid'];

			$creation_clock = $creation_times[$userid] ?? null;

			/*
			 * If creation information cannot be found, don't
			 * automatically classify the account as inactive.
			 *
			 * We will handle these separately as "Creation time
			 * not found".
			 */
			if ($creation_clock === null) {
				$user['creation_clock'] = null;
				$user['account_age_days'] = null;
				$user['candidate'] = false;
				$user['candidate_reason'] = 'creation_time_not_found';

				$candidate_users[] = $user;

				continue;
			}

			$account_age_seconds = $current_time - $creation_clock;

			$account_age_days = floor(
				$account_age_seconds / 86400
			);

			$user['creation_clock'] = $creation_clock;
			$user['account_age_days'] = $account_age_days;

			/*
			 * Candidate if account is at least 60 days old.
			 */
			if ($account_age_days >= $account_age_threshold) {
				$user['candidate'] = true;
				$user['candidate_reason'] = 'account_older_than_threshold';

				$candidate_users[] = $user;
			}
		}

		/*
		 * ------------------------------------------------------------
		 * 5. Send data to view
		 * ------------------------------------------------------------
		 */
		$data = [
			'title' => _('User Management'),

			/*
			 * All enabled users.
			 */
			'total_users' => count($users),

			/*
			 * Only users requiring further investigation.
			 */
			'candidate_users' => $candidate_users,

			/*
			 * Useful during development/debugging.
			 */
			'creation_times' => $creation_times,

			'account_age_threshold' => $account_age_threshold,

			'current_time' => $current_time
		];

		$this->setResponse(new CControllerResponseData($data));
	}
}
