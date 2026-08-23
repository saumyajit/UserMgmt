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
		 * action       = 0  -> Add
		 * resourcetype = 0  -> User
		 *
		 * resourceid   = newly created user's userid
		 * resourcename = newly created user's username
		 * userid       = user who created the account
		 * clock        = account creation time
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
			'sortorder' => ZBX_SORT_DOWN
		]);

		/*
		 * ------------------------------------------------------------
		 * 3. Build:
		 *
		 *     userid => creation timestamp
		 *
		 * Example:
		 *
		 *     744 => 1729154939
		 * ------------------------------------------------------------
		 */
		$creation_times = [];

		foreach ($user_creation_logs as $audit) {
			$userid = (string) $audit['resourceid'];

			/*
			 * Since records are sorted DESC, the first occurrence
			 * is the latest creation record for that resource.
			 */
			if (!isset($creation_times[$userid])) {
				$creation_times[$userid] = (int) $audit['clock'];
			}
		}

		/*
		 * ------------------------------------------------------------
		 * 4. Attach creation timestamp to each current user
		 * ------------------------------------------------------------
		 */
		foreach ($users as &$user) {
			$userid = (string) $user['userid'];

			$user['creation_clock'] = $creation_times[$userid] ?? null;
		}
		unset($user);

		/*
		 * ------------------------------------------------------------
		 * 5. Get recent successful login records
		 *
		 * action       = 8  -> Login
		 * resourcetype = 0  -> User
		 *
		 * We are deliberately limiting this during the testing phase.
		 * Once we confirm the exact login correlation, we will change
		 * the retrieval strategy for the production implementation.
		 * ------------------------------------------------------------
		 */
		$login_logs = API::AuditLog()->get([
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
				'action' => 8,
				'resourcetype' => 0
			],
			'sortfield' => 'clock',
			'sortorder' => ZBX_SORT_DOWN,
			'limit' => 50
		]);

		/*
		 * ------------------------------------------------------------
		 * 6. Build latest login information
		 *
		 * We are NOT yet using this to calculate the policy.
		 *
		 * We first want to verify that the userid/resourceid
		 * relationship is correct in your environment.
		 * ------------------------------------------------------------
		 */
		$last_login_times = [];

		foreach ($login_logs as $login) {
			$userid = (string) $login['userid'];

			if (!isset($last_login_times[$userid])) {
				$last_login_times[$userid] = (int) $login['clock'];
			}
		}

		/*
		 * ------------------------------------------------------------
		 * 7. Attach last login timestamp to users
		 * ------------------------------------------------------------
		 */
		foreach ($users as &$user) {
			$userid = (string) $user['userid'];

			$user['last_login_clock'] = $last_login_times[$userid] ?? null;
		}
		unset($user);

		/*
		 * ------------------------------------------------------------
		 * 8. Send everything to the view
		 * ------------------------------------------------------------
		 */
		$data = [
			'title' => _('User Management'),

			'users' => $users,

			'creation_logs' => $user_creation_logs,

			'creation_times' => $creation_times,

			'login_logs' => $login_logs,

			'last_login_times' => $last_login_times
		];

		$this->setResponse(new CControllerResponseData($data));
	}
}
