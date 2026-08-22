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

		$auditlog = API::AuditLog()->get([
			'output' => 'extend',
			'sortfield' => 'clock',
			'sortorder' => ZBX_SORT_DOWN,
			'filter' => [
				'action' => 0,
				'resourcetype' => 0
			],
			'limit' => 1
		]);

		$data = [
			'title' => _('User Management'),
			'users' => $users,
			'auditlog' => $auditlog
		];

		$this->setResponse(new CControllerResponseData($data));
	}
}
