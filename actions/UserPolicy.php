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

		$data = [
			'title' => _('User Management'),
			'users' => $users
		];

		$this->setResponse(new CControllerResponseData($data));
	}
}
