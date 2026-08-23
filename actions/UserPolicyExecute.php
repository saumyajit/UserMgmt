<?php

namespace Modules\UserMgmt\Actions;

use API;
use CController;
use CControllerResponseRedirect;
use CUrl;

class UserPolicyExecute extends CController {

	protected function checkInput(): bool {

		$fields = [
			'userids' => 'array'
		];

		return $this->validateInput($fields);
	}


	protected function checkPermissions(): bool {

		return $this->getUserType() >= USER_TYPE_SUPER_ADMIN;
	}


	protected function doAction(): void {

		$userids = $this->getInput('userids', []);


		/*
		 * --------------------------------------------------------
		 * Nothing selected.
		 * --------------------------------------------------------
		 */

		if (!$userids) {

			error(_('No users were selected.'));

			$url = (new CUrl('zabbix.php'))
				->setArgument('action', 'user.policy');

			$this->setResponse(
				new CControllerResponseRedirect($url)
			);

			return;
		}


		/*
		 * --------------------------------------------------------
		 * Normalize IDs.
		 * --------------------------------------------------------
		 */

		$userids = array_values(
			array_unique(
				array_map('strval', $userids)
			)
		);


		/*
		 * --------------------------------------------------------
		 * IMPORTANT SAFETY CHECK
		 *
		 * Never blindly trust IDs submitted by the browser.
		 *
		 * Re-evaluate the users before disabling them.
		 * --------------------------------------------------------
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
			'userids' => $userids
		]);


		/*
		 * --------------------------------------------------------
		 * Only currently enabled users are allowed.
		 * --------------------------------------------------------
		 */

		$enabled_userids = [];

		foreach ($users as $user) {

			if ((int) $user['status'] === 0) {
				$enabled_userids[] = (string) $user['userid'];
			}
		}


		if (!$enabled_userids) {

			error(_('No selected users are currently enabled.'));

			$url = (new CUrl('zabbix.php'))
				->setArgument('action', 'user.policy');

			$this->setResponse(
				new CControllerResponseRedirect($url)
			);

			return;
		}


		/*
		 * --------------------------------------------------------
		 * DO NOT DISABLE YET.
		 *
		 * This is our first execution test.
		 *
		 * We will replace this block with the policy
		 * re-validation + user.update() after confirming
		 * the POST/redirect workflow.
		 * --------------------------------------------------------
		 */

		$message = _s(
			'%1$d enabled user(s) selected for policy execution.',
			count($enabled_userids)
		);

		info($message);


		/*
		 * --------------------------------------------------------
		 * Redirect back.
		 * --------------------------------------------------------
		 */

		$url = (new CUrl('zabbix.php'))
			->setArgument('action', 'user.policy');

		$this->setResponse(
			new CControllerResponseRedirect($url)
		);
	}
}
