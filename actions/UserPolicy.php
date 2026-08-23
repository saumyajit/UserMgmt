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
		* Get user creation audit records.
		*
		* action = 0        -> Add
		* resourcetype = 0  -> User
		*/
		$user_creation_logs = API::AuditLog()->get([
			'output' => [
				'auditid',
				'userid',
				'clock',
				'action',
				'resourcetype',
				'resourceid',
				'resourcename',
				'username'
			],
			'filter' => [
				'action' => 0,
				'resourcetype' => 0
			],
			'sortfield' => 'clock',
			'sortorder' => ZBX_SORT_DOWN
		]);
	
		/*
		* Build:
		*
		* userid => creation timestamp
		*/
		$creation_times = [];
	
		foreach ($user_creation_logs as $audit) {
			$userid = (string) $audit['resourceid'];
	
			/*
			* Keep the first/latest matching creation record.
			* Since records are sorted DESC, this protects us
			* against unexpected duplicate entries.
			*/
			if (!isset($creation_times[$userid])) {
				$creation_times[$userid] = (int) $audit['clock'];
			}
		}
	
		/*
		* Attach creation timestamp to every current user.
		*/
		foreach ($users as &$user) {
			$userid = (string) $user['userid'];
	
			$user['creation_clock'] = $creation_times[$userid] ?? null;
		}
		unset($user);
	
		$data = [
			'title' => _('User Management'),
			'users' => $users,
			'creation_logs' => $user_creation_logs,
			'creation_times' => $creation_times
		];
	
		$this->setResponse(new CControllerResponseData($data));
	}
}
