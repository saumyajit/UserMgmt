<?php declare(strict_types = 1);

namespace Modules\UserPolicy\Actions;

use API;
use CController;
use CControllerResponseData;
use CRoleHelper;

class UserPolicy extends CController {
	protected function checkInput(): bool {
		$ret = $this->validateInput([
			'disable_days' => 'int32',
			'delete_days' => 'int32',
			'dry_run' => 'in 0,1'
		]);

		if (!$ret) {
			$this->setResponse(new \CControllerResponseFatal());
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() == USER_TYPE_SUPER_ADMIN;
	}

	protected function doAction(): void {
		$disable_days = $this->getInput('disable_days', 45);
		$delete_days = $this->getInput('delete_days', 90);
		$dry_run = $this->getInput('dry_run', 1);

		if ($disable_days < 1) {
			$disable_days = 45;
		}

		if ($delete_days <= $disable_days) {
			$delete_days = 90;
		}

		$users = API::User()->get([
			'output' => [
				'userid',
				'username',
				'name',
				'surname',
				'attempt_clock',
				'roleid'
			],
			'getAccess' => true,
			'selectUsrgrps' => [
				'usrgrpid',
				'name',
				'users_status',
				'gui_access'
			],
			'selectRole' => [
				'roleid',
				'name',
				'type',
				'readonly'
			],
			'sortfield' => 'username',
			'sortorder' => 'ASC'
		]);

		$now = time();
		$disable_before = $now - ($disable_days * 86400);
		$delete_before = $now - ($delete_days * 86400);

		$rows = [];

		foreach ($users as $user) {
			$userid = (string) $user['userid'];

			/*
			 * Zabbix does not provide a universal last-successful-login
			 * property through user.get. This value is populated from the
			 * latest relevant audit-log record.
			 */
			$created_at = $this->getUserCreationTime($userid);
			$last_activity = $this->getLastUserActivityTime($userid);

			$already_disabled = $this->isUserDisabled($user);

			$decision = 'No action';
			$action = 'none';
			$reason = 'User does not meet policy conditions.';

			if ($userid === (string) CWebUser::$data['userid']) {
				$decision = 'Protected';
				$reason = 'Currently logged-in user is protected from automated changes.';
			}
			elseif ($this->isProtectedUser($user)) {
				$decision = 'Protected';
				$reason = 'Built-in or protected administrative account.';
			}
			elseif ($created_at === null) {
				$decision = 'Review';
				$reason = 'Creation time was not found in the available audit log.';
			}
			elseif ($last_activity === null && $created_at <= $delete_before) {
				$decision = 'Delete candidate';
				$action = 'delete';
				$reason = 'No login activity found and account is older than the deletion threshold.';
			}
			elseif ($last_activity === null && $created_at <= $disable_before) {
				$decision = 'Disable candidate';
				$action = 'disable';
				$reason = 'No login activity found and account is older than the disable threshold.';
			}
			elseif ($last_activity !== null && $last_activity <= $delete_before) {
				$decision = 'Delete candidate';
				$action = 'delete';
				$reason = 'Last recorded activity is older than the deletion threshold.';
			}
			elseif ($last_activity !== null && $last_activity <= $disable_before) {
				$decision = 'Disable candidate';
				$action = 'disable';
				$reason = 'Last recorded activity is older than the disable threshold.';
			}

			if ($already_disabled && $action === 'disable') {
				$decision = 'Already disabled';
				$action = 'none';
				$reason = 'User is already disabled.';
			}

			$rows[] = [
				'userid' => $userid,
				'username' => $user['username'],
				'name' => trim($user['name'].' '.$user['surname']),
				'role' => $user['role']['name'] ?? '',
				'created_at' => $created_at,
				'last_activity' => $last_activity,
				'created_text' => $this->formatTimestamp($created_at),
				'last_activity_text' => $this->formatTimestamp($last_activity),
				'disabled' => $already_disabled,
				'decision' => $decision,
				'action' => $action,
				'reason' => $reason
			];
		}

		$this->setResponse(new CControllerResponseData([
			'users' => $rows,
			'disable_days' => $disable_days,
			'delete_days' => $delete_days,
			'dry_run' => $dry_run,
			'generated_at' => time(),
			'disable_before' => $disable_before,
			'delete_before' => $delete_before
		]));
	}

	private function getUserCreationTime(string $userid): ?int {
		$records = API::AuditLog()->get([
			'output' => ['clock'],
			'filter' => [
				'action' => 0,
				'resourcetype' => 0,
				'resourceid' => $userid
			],
			'sortfield' => 'clock',
			'sortorder' => 'ASC',
			'limit' => 1
		]);

		if (!$records) {
			return null;
		}

		return (int) $records[0]['clock'];
	}

	private function getLastUserActivityTime(string $userid): ?int {
		/*
		 * This searches audit entries where the user itself performed
		 * an operation. It does not guarantee that every login event is
		 * audited in every Zabbix version/configuration.
		 *
		 * If your audit-log format records successful authentication with
		 * a dedicated action/resource type, add that filter here.
		 */
		$records = API::AuditLog()->get([
			'output' => ['clock'],
			'filter' => [
				'userid' => $userid
			],
			'sortfield' => 'clock',
			'sortorder' => 'DESC',
			'limit' => 1
		]);

		if (!$records) {
			return null;
		}

		return (int) $records[0]['clock'];
	}

	private function isUserDisabled(array $user): bool {
		if (!empty($user['users_status'])) {
			return true;
		}

		foreach ($user['usrgrps'] ?? [] as $group) {
			if ((int) ($group['users_status'] ?? 0) === 1) {
				return true;
			}
		}

		return false;
	}

	private function isProtectedUser(array $user): bool {
		$username = strtolower((string) $user['username']);

		if (in_array($username, ['admin', 'guest'], true)) {
			return true;
		}

		/*
		 * Protect Super Admin accounts by default. Remove this block only
		 * if your policy explicitly allows automatic handling of them.
		 */
		if (($user['role']['type'] ?? null) == USER_TYPE_SUPER_ADMIN) {
			return true;
		}

		return false;
	}

	private function formatTimestamp(?int $timestamp): string {
		return $timestamp === null
			? _('Never / not found')
			: zbx_date2str(DATE_TIME_FORMAT_SECONDS, $timestamp);
	}
}
