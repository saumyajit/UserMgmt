<?php

namespace Modules\UserMgmt\Actions;

use API;
use CController;
use CControllerResponseData;

class UserPolicy extends CController {

	const MIN_ACCOUNT_AGE_DAYS = 60;
	const INACTIVITY_THRESHOLD_DAYS = 45;

	// Kept as a plain data file path + private static helpers, deliberately NOT a separate
	// class in its own namespace — that indirection is what caused load failures earlier.
	// Everything this module needs to run lives in these two controller files.
	private static function queueFile(): string {
		return __DIR__ . '/../data/approval_queue.json';
	}

	private static function loadQueue(): array {
		$file = self::queueFile();
		if (!is_file($file)) {
			return [];
		}
		$data = json_decode((string) file_get_contents($file), true);
		return is_array($data) ? $data : [];
	}

	private static function isPending(string $userid): bool {
		foreach (self::loadQueue() as $entry) {
			if (($entry['userid'] ?? null) === $userid && ($entry['status'] ?? null) === 'pending') {
				return true;
			}
		}
		return false;
	}

	public function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		return $this->validateInput([
			'filter_username'       => 'string',
			'filter_activity'       => 'in all,never,logged_in,inactive',
			'filter_account_age'    => 'in all,gt60,lt60',
			'filter_recommendation' => 'in all,disable,no_action',
			'filter_status'         => 'in all,enabled,disabled',
			'filter_rst'            => 'in 1'
		]);
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() >= USER_TYPE_SUPER_ADMIN;
	}

	protected function doAction(): void {
		$users = API::User()->get([
			'output' => ['userid', 'username', 'name', 'surname', 'roleid'],
			'selectUsrgrps' => ['usrgrpid', 'name'],
			'sortfield' => 'username',
			'sortorder' => ZBX_SORT_UP
		]);

		// A user is "disabled" if every group they belong to has gui_access DISABLED —
		// Zabbix stores that flag on the group, not the user.
		$usrgrpids = [];
		foreach ($users as $user) {
			foreach ($user['usrgrps'] as $grp) {
				$usrgrpids[$grp['usrgrpid']] = true;
			}
		}
		$group_access = [];
		if ($usrgrpids) {
			foreach (API::UserGroup()->get([
				'output' => ['usrgrpid', 'gui_access'],
				'usrgrpids' => array_keys($usrgrpids)
			]) as $group) {
				$group_access[$group['usrgrpid']] = (int) $group['gui_access'];
			}
		}

		// Creation time per user: action=0 (Add), resourcetype=0 (User) audit records.
		// The User API has no creation-time field, so this is the only source.
		$creation_times = [];
		foreach (API::AuditLog()->get([
			'output' => ['clock', 'resourceid'],
			'filter' => ['action' => 0, 'resourcetype' => 0],
			'sortfield' => 'clock',
			'sortorder' => ZBX_SORT_DOWN
		]) as $audit) {
			$userid = (string) $audit['resourceid'];
			if (!isset($creation_times[$userid])) {
				$creation_times[$userid] = (int) $audit['clock'];
			}
		}

		// Last successful login per user: action=8 (Login), resourcetype=0 (User).
		$last_login_times = [];
		foreach (API::AuditLog()->get([
			'output' => ['userid', 'clock'],
			'filter' => ['action' => 8, 'resourcetype' => 0],
			'sortfield' => 'clock',
			'sortorder' => ZBX_SORT_DOWN,
			'limit' => 1000
		]) as $login) {
			$userid = (string) $login['userid'];
			if (!isset($last_login_times[$userid])) {
				$last_login_times[$userid] = (int) $login['clock'];
			}
		}

		$now = time();
		$day = 86400;
		$counts = ['never_logged_in' => 0, 'inactive_45' => 0, 'recommend_disable' => 0];

		foreach ($users as &$user) {
			$userid = (string) $user['userid'];
			$creation_clock = $creation_times[$userid] ?? null;
			$last_login_clock = $last_login_times[$userid] ?? null;

			$account_age_days = $creation_clock !== null ? intdiv($now - $creation_clock, $day) : null;
			$inactive_days = $last_login_clock !== null ? intdiv($now - $last_login_clock, $day) : null;

			$is_disabled = (bool) $user['usrgrps'];
			foreach ($user['usrgrps'] as $grp) {
				if (($group_access[$grp['usrgrpid']] ?? GROUP_GUI_ACCESS_SYSTEM) != GROUP_GUI_ACCESS_DISABLED) {
					$is_disabled = false;
					break;
				}
			}

			// Policy: age > 60d AND (never logged in OR inactive > 45d) -> disable. Else no action.
			// Unknown creation time -> no action (conservative, not guessed).
			$recommendation = 'no_action';
			if ($account_age_days !== null && $account_age_days > self::MIN_ACCOUNT_AGE_DAYS) {
				if ($last_login_clock === null) {
					$recommendation = 'disable';
					$counts['never_logged_in']++;
				}
				elseif ($inactive_days > self::INACTIVITY_THRESHOLD_DAYS) {
					$recommendation = 'disable';
					$counts['inactive_45']++;
				}
			}
			elseif ($last_login_clock === null) {
				$counts['never_logged_in']++;
			}
			if ($recommendation === 'disable') {
				$counts['recommend_disable']++;
			}

			$user['creation_clock'] = $creation_clock;
			$user['last_login_clock'] = $last_login_clock;
			$user['account_age_days'] = $account_age_days;
			$user['inactive_days'] = $inactive_days;
			$user['recommendation'] = $recommendation;
			$user['is_disabled'] = $is_disabled;
			$user['pending_approval'] = self::isPending($userid);
		}
		unset($user);

		$filter = [
			'username'       => $this->getInput('filter_username', ''),
			'activity'       => $this->getInput('filter_activity', 'all'),
			'account_age'    => $this->getInput('filter_account_age', 'all'),
			'recommendation' => $this->getInput('filter_recommendation', 'all'),
			'status'         => $this->getInput('filter_status', 'enabled')
		];
		if ($this->hasInput('filter_rst')) {
			$filter = ['username' => '', 'activity' => 'all', 'account_age' => 'all',
				'recommendation' => 'all', 'status' => 'enabled'];
		}

		$filtered = array_values(array_filter($users, function($u) use ($filter) {
			if ($filter['username'] !== '' && stripos($u['username'], $filter['username']) === false) {
				return false;
			}
			if ($filter['activity'] === 'never' && $u['last_login_clock'] !== null) return false;
			if ($filter['activity'] === 'logged_in' && $u['last_login_clock'] === null) return false;
			if ($filter['activity'] === 'inactive'
					&& !($u['inactive_days'] !== null && $u['inactive_days'] > self::INACTIVITY_THRESHOLD_DAYS)) return false;
			if ($filter['account_age'] === 'gt60'
					&& !($u['account_age_days'] !== null && $u['account_age_days'] > self::MIN_ACCOUNT_AGE_DAYS)) return false;
			if ($filter['account_age'] === 'lt60'
					&& !($u['account_age_days'] !== null && $u['account_age_days'] <= self::MIN_ACCOUNT_AGE_DAYS)) return false;
			if ($filter['recommendation'] !== 'all' && $u['recommendation'] !== $filter['recommendation']) return false;
			if ($filter['status'] === 'enabled' && $u['is_disabled']) return false;
			if ($filter['status'] === 'disabled' && !$u['is_disabled']) return false;
			return true;
		}));

		$this->setResponse(new CControllerResponseData([
			'title' => _('User Management'),
			'users' => $filtered,
			'all_users_count' => count($users),
			'counts' => $counts,
			'filter' => $filter,
			'policy' => [
				'min_account_age_days' => self::MIN_ACCOUNT_AGE_DAYS,
				'inactivity_threshold_days' => self::INACTIVITY_THRESHOLD_DAYS
			]
		]));
	}
}
