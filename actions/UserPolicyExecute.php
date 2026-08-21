<?php declare(strict_types = 1);

namespace Modules\UserPolicy\Actions;

use API;
use CController;
use CControllerResponseData;
use CControllerResponseFatal;
use CWebUser;
use Throwable;

class UserPolicyExecute extends CController {
	protected function checkInput(): bool {
		$ret = $this->validateInput([
			'selected' => 'array',
			'operation' => 'in disable,delete',
			'confirm' => 'in 0,1',
			'dry_run' => 'in 0,1'
		]);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return CWebUser::getType() == USER_TYPE_SUPER_ADMIN;
	}

	protected function doAction(): void {
		$selected = array_map('strval', $this->getInput('selected', []));
		$operation = $this->getInput('operation', 'disable');
		$dry_run = (int) $this->getInput('dry_run', 1);
		$processed = [];
		$errors = [];

		foreach ($selected as $userid) {
			$user = API::User()->get([
				'output' => [
					'userid',
					'username',
					'name',
					'surname',
					'roleid'
				],
				'selectRole' => [
					'roleid',
					'name',
					'type',
					'readonly'
				],
				'userids' => $userid
			]);

			if (!$user) {
				$errors[] = sprintf(_('User ID %s was not found.'), $userid);
				continue;
			}

			$user = $user[0];

			if ($userid === (string) CWebUser::$data['userid']) {
				$errors[] = sprintf(_('Current user %s was skipped.'), $user['username']);
				continue;
			}

			if ($this->isProtectedUser($user)) {
				$errors[] = sprintf(_('Protected user %s was skipped.'), $user['username']);
				continue;
			}

			if ($dry_run) {
				$processed[] = [
					'userid' => $userid,
					'username' => $user['username'],
					'operation' => $operation,
					'status' => 'dry-run'
				];
				continue;
			}

			if ($operation === 'delete') {
				try {
					API::User()->delete([$userid]);
					$processed[] = [
						'userid' => $userid,
						'username' => $user['username'],
						'operation' => 'delete',
						'status' => 'completed'
					];
				}
				catch (Throwable $e) {
					$errors[] = sprintf(_('Delete failed for %s: %s'), $user['username'], $e->getMessage());
				}
			}
			else {
				$errors[] = sprintf(
					_('Disable skipped for %s: disabling is group-level in Zabbix.'),
					$user['username']
				);
			}
		}

		$this->setResponse(new CControllerResponseData([
			'processed' => $processed,
			'errors' => $errors,
			'operation' => $operation,
			'dry_run' => $dry_run
		]));
	}

	private function isProtectedUser(array $user): bool {
		$username = strtolower((string) $user['username']);

		return in_array($username, ['admin', 'guest'], true)
			|| (($user['role']['type'] ?? null) == USER_TYPE_SUPER_ADMIN);
	}
}
