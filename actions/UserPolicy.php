<?php

namespace Modules\UserMgmt\Actions;

use CController;
use CControllerResponseData;

class UserPolicy extends CController {

	protected function checkInput(): bool {
		return true;
	}

	protected function checkPermissions(): bool {
		return true;
	}

	protected function doAction(): void {
		$data = [
			'title' => _('User Management'),
			'message' => _('User Management module is working successfully.')
		];

		$response = new CControllerResponseData($data);
		$response->setTitle(_('User Management'));

		$this->setResponse($response);
	}
}
