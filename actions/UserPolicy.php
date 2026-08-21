<?php declare(strict_types = 1);

namespace Modules\UserPolicy\Actions;

use API;
use CController;
use CControllerResponseData;
use CControllerResponseFatal;
use CWebUser;

class UserPolicy extends CController {
	protected function checkInput(): bool {
		$ret = $this->validateInput([
			'disable_days' => 'int32',
			'delete_days' => 'int32'
		]);

		if (!$ret) {
			$this->setResponse(new CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() == USER_TYPE_SUPER_ADMIN;
	}

	protected function doAction(): void {
		$disable_days = max(1, $this->getInput('disable_days', 45));
		$delete_days = max($disable_days + 1, $this->getInput('delete_days', 90));
		$now = time();
		$disable_before = $now - $disable_days * 86400;
		$delete_before = $now - $delete_days * 86400;

		$users = API::User()->get([
			'output' => [
				'userid', 'username', 'name', 'surname', 'attempt_clock', 'roleid'
			],
			'selectRole' => ['roleid', 'name', 'type', 'readonly'],
			'selectUsrgrps' => ['usrgrpid', 'name', 'users_status', 'gui_access'],
			'sortfield' => 'username',
			'sortorder' => 'ASC'
		]);

		$rows = [];

		foreach ($users as $user) {
			$userid = (string) $user['userid'];
			$created_at = $this->getUserCreationTime($userid);
			$last_activity = $this->getLastActivityTime($userid);
			$is_current = $userid === (string) CWebUser::$data['userid'];
			$is_protected = $is_current || $this->isProtectedUser($user);
			$is_disabled = $this->isDisabled($user);

			$action = 'none';
			$decision = _('No action');
			$reason = _('The user does not meet the policy threshold.');

			if ($is_protected) {
				$decision = _('Protected');
				$reason = $is_current
					? _('The currently logged-in user is protected.')
					: _('Administrative or built-in account.');
			}
			elseif ($created_at === null) {
				$decision = _('Review');
				$reason = _('Creation record was not found in the retained audit log.');
			}
			elseif ($last_activity === null && $created_at <= $delete_before) {
				$action = 'delete';
				$decision = _('Delete candidate');
				$reason = _('No activity was found and the account is older than the delete threshold.');
			}
			elseif ($last_activity === null && $created_at <= $disable_before) {
				$action = 'disable';
				$decision = _('Disable candidate');
				$reason = _('No activity was found and the account is older than the disable threshold.');
			}
			elseif ($last_activity !== null && $last_activity <= $delete_before) {
				$action = 'delete';
				$decision = _('Delete candidate');
				$reason = _('Last audit activity is older than the delete threshold.');
			}
			elseif ($last_activity !== null && $last_activity <= $disable_before) {
				$action = 'disable';
				$decision = _('Disable candidate');
				$reason = _('Last audit activity is older than the disable threshold.');
			}

			if ($is_disabled && $action === 'disable') {
				$action = 'none';
				$decision = _('Already disabled');
				$reason = _('The user is already disabled through its user-group access.');
			}

			$rows[] = [
				'userid' => $userid,
				'username' => $user['username'],
				'name' => trim($user['name'].' '.$user['surname']),
				'role' => $user['role']['name'] ?? '',
				'created_text' => $this->formatTime($created_at),
				'last_activity_text' => $this->formatTime($last_activity),
				'disabled' => $is_disabled,
				'decision' => $decision,
				'action' => $action,
				'reason' => $reason
			];
		}

		$this->setResponse(new CControllerResponseData([
			'users' => $rows,
			'disable_days' => $disable_days,
			'delete_days' => $delete_days,
			'generated_at' => $now
		]));
	}

	private function getUserCreationTime(string $userid): ?int {
		$records = API::AuditLog()->get([
			'output' => ['clock', 'action', 'resourcetype', 'resourceid', 'details'],
			'filter' => [
				'action' => 0,
				'resourcetype' => 0,
				'resourceid' => $userid
			],
			'sortfield' => 'clock',
			'sortorder' => 'ASC',
			'limit' => 1
		]);

		return $records ? (int) $records[0]['clock'] : null;
	}

	private function getLastActivityTime(string $userid): ?int {
		$records = API::AuditLog()->get([
			'output' => ['clock'],
			'filter' => ['userid' => $userid],
			'sortfield' => 'clock',
			'sortorder' => 'DESC',
			'limit' => 1
		]);

		return $records ? (int) $records[0]['clock'] : null;
	}

	private function isDisabled(array $user): bool {
		foreach ($user['usrgrps'] ?? [] as $group) {
			if ((int) ($group['users_status'] ?? 0) === 1) {
				return true;
			}
		}

		return false;
	}

	private function isProtectedUser(array $user): bool {
		$username = strtolower((string) $user['username']);

		return in_array($username, ['admin', 'guest'], true)
			|| (($user['role']['type'] ?? null) == USER_TYPE_SUPER_ADMIN);
	}

	private function formatTime(?int $timestamp): string {
		return $timestamp === null
			? _('Not found')
			: zbx_date2str(DATE_TIME_FORMAT_SECONDS, $timestamp);
	}
}
