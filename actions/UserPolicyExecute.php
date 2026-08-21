<?php declare(strict_types = 1);

namespace Modules\UserPolicy\Actions;

use API;
use CController;
use CControllerResponseData;

class UserPolicyExecute extends CController {
	protected function checkInput(): bool {
		$ret = $this->validateInput([
			'selected' => 'array',
			'operation' => 'in disable,delete',
			'confirm' => 'in 0,1',
			'dry_run' => 'in 0,1'
		]);

		if (!$ret) {
			$this->setResponse(new \CControllerResponseFatal());
			return false;
		}

		if (!$this->getInput('confirm', 0) && !$this->getInput('dry_run', 1)) {
			error(_('Confirmation is required before making changes.'));
			$ret = false;
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() == USER_TYPE_SUPER_ADMIN;
	}

	protected function doAction(): void {
		$selected = array_map('strval', $this->getInput('selected', []));
		$operation = $this->getInput('operation', 'disable');
		$dry_run = (int) $this->getInput('dry_run', 1);

		$processed = [];
		$errors = [];

		foreach ($selected as $userid) {
			try {
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
					$errors[] = sprintf(_('User %s is the current account and was skipped.'), $user['username']);
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

				if ($operation === 'disable') {
					/*
					 * Important: Zabbix user disabling is group-based.
					 * This updates the user's groups while preserving group
					 * membership, changing users_status for each group.
					 *
					 * Do not use this section if those groups are shared by
					 * active users, because it disables access for all members.
					 */
					$groups = API::UserGroup()->get([
						'output' => ['usrgrpid'],
						'selectUsers' => ['userid'],
						'filter' => [],
						'preservekeys' => false
					]);

					$user_groups = [];

					foreach ($groups as $group) {
						foreach ($group['users'] ?? [] as $group_user) {
							if ((string) $group_user['userid'] === $userid) {
								$user_groups[] = [
									'usrgrpid' => $group['usrgrpid'],
									'users_status' => 1
								];
								break;
							}
						}
					}

					if (!$user_groups) {
						$errors[] = sprintf(
							_('User %s has no resolvable user groups and was skipped.'),
							$user['username']
						);
						continue;
					}

					/*
					 * Group-level disabling is dangerous. The safer production
					 * design is to create a dedicated disabled group per user,
					 * move the user to it, and remove the original groups.
					 *
					 * This example reports the limitation instead of changing
					 * shared groups automatically.
					 */
					$errors[] = sprintf(
						_('User %s was not disabled because Zabbix disables users through user groups.'),
						$user['username']
					);
				}
				elseif ($operation === 'delete') {
					$result = API::User()->delete([$userid]);

					if (!$result) {
						$errors[] = sprintf(_('Failed to delete user %s.'), $user['username']);
						continue;
					}

					$processed[] = [
						'userid' => $userid,
						'username' => $user['username'],
						'operation' => 'delete',
						'status' => 'completed'
					];
				}
			}
			catch (\Throwable $e) {
				$errors[] = sprintf(
					_('User ID %s failed: %s'),
					$userid,
					$e->getMessage()
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

		if (in_array($username, ['admin', 'guest'], true)) {
			return true;
		}

		return ($user['role']['type'] ?? null) == USER_TYPE_SUPER_ADMIN;
	}
}
