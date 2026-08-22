<?php

namespace Modules\UserMgmt\Actions;

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
		$data = [
			'title' => _('User Management'),
			'message' => _('User Management module is working successfully.')
		];

		$this->setResponse(new CControllerResponseData($data));
	}
}
