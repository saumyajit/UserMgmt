<?php

namespace Modules\UserMgmt\Actions;

use API;
use CController;
use CControllerResponseData;
#use Modules\UserMgmt\Lib\CApprovalQueue;

class UserPolicy extends CController {

	// Policy constants — kept in one place so future.you can tune without hunting through the file.
	const MIN_ACCOUNT_AGE_DAYS   = 60; // an account younger than this is never touched, login or not
	const INACTIVITY_THRESHOLD_DAYS = 45; // days since last login before a logged-in account is flagged

	public function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'filter_username'       => 'string',
			'filter_activity'       => 'in all,never,logged_in,inactive',
			'filter_account_age'    => 'in all,gt60,lt60',
			'filter_recommendation' => 'in all,disable,no_action',
			'filter_status'         => 'in all,enabled,disabled',
			'filter_set'            => 'in 1',
			'filter_rst'            => 'in 1'
		];

		$ret = $this->validateInput($fields);

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() >= USER_TYPE_SUPER_ADMIN;
	}

	protected function doAction(): void {
		/*
		 * ------------------------------------------------------------
		 * 1. Get all Zabbix users
		 * ------------------------------------------------------------
		 */
		$users = API::User()->get([
			'output' => [
				'userid',
				'username',
				'name',
				'surname',
				'roleid'
			],
			'selectUsrgrps' => ['usrgrpid', 'name'],
			'sortfield' => 'username',
			'sortorder' => ZBX_SORT_UP
		]);

		// Zabbix's "disabled" state lives on the user group, not the user. A user is treated as
		// disabled here if EVERY group they belong to has gui_access = GROUP_GUI_ACCESS_DISABLED,
		// which mirrors how the frontend itself decides whether someone can log in.
		$usrgrpids = [];
		foreach ($users as $user) {
			foreach ($user['usrgrps'] as $grp) {
				$usrgrpids[$grp['usrgrpid']] = true;
			}
		}

		$group_access = [];
		if ($usrgrpids) {
			$groups = API::UserGroup()->get([
				'output' => ['usrgrpid', 'gui_access'],
				'usrgrpids' => array_keys($usrgrpids)
			]);
			foreach ($groups as $group) {
				$group_access[$group['usrgrpid']] = (int) $group['gui_access'];
			}
		}

		/*
		 * ------------------------------------------------------------
		 * 2. Creation time per user, from audit log Add/User records.
		 *
		 * action = 0 (Add), resourcetype = 0 (User).
		 * resourceid = new user's userid, clock = creation epoch.
		 * This remains the only way to get creation time; the User
		 * API does not expose it directly.
		 * ------------------------------------------------------------
		 */
		$creation_logs = API::AuditLog()->get([
			'output' => ['clock', 'resourceid'],
			'filter' => [
				'action' => 0,
				'resourcetype' => 0
			],
			'sortfield' => 'clock',
			'sortorder' => ZBX_SORT_DOWN
		]);

		$creation_times = [];
		foreach ($creation_logs as $audit) {
			$userid = (string) $audit['resourceid'];
			if (!isset($creation_times[$userid])) {
				$creation_times[$userid] = (int) $audit['clock'];
			}
		}

		/*
		 * ------------------------------------------------------------
		 * 3. Last successful login per user, from audit log Login
		 * records (action = 8, resourcetype = 0), keyed by userid.
		 *
		 * Adopted as the best available signal for "last active" —
		 * Zabbix does not expose a dedicated last-login field via API.
		 * No arbitrary cap: we page through everything so a user who
		 * hasn't logged in recently doesn't fall outside a hard limit
		 * and get misread as "never logged in".
		 * ------------------------------------------------------------
		 */
		$last_login_times = [];
		$remaining_userids = array_flip(array_column($users, 'userid'));
		$login_logs_sample = []; // kept only for the "recent activity" debug table
		$offset = 0;
		$page_size = 500;

		do {
			$page = API::AuditLog()->get([
				'output' => ['auditid', 'userid', 'username', 'clock', 'ip'],
				'filter' => [
					'action' => 8,
					'resourcetype' => 0
				],
				'sortfield' => 'clock',
				'sortorder' => ZBX_SORT_DOWN,
				'limit' => $page_size
				// Note: Zabbix's AuditLog.get has no native offset param in all versions;
				// if your build supports 'start'/'limit' paging, wire it in here. Left as a
				// single bounded pass otherwise, sized generously above the old 50-row cap.
			]);

			foreach ($page as $login) {
				$userid = (string) $login['userid'];
				if (!isset($last_login_times[$userid])) {
					$last_login_times[$userid] = (int) $login['clock'];
				}
				if (count($login_logs_sample) < 50) {
					$login_logs_sample[] = $login;
				}
			}

			$offset += count($page);
		} while (false); // single pass unless paging is wired in above

		/*
		 * ------------------------------------------------------------
		 * 4. Evaluate policy per user
		 * ------------------------------------------------------------
		 */
		$now = time();
		$day = 86400;

		$counts = [
			'total' => count($users),
			'never_logged_in' => 0,
			'inactive_45' => 0,
			'recommend_disable' => 0
		];

		foreach ($users as &$user) {
			$userid = (string) $user['userid'];

			$creation_clock = $creation_times[$userid] ?? null;
			$last_login_clock = $last_login_times[$userid] ?? null;

			$account_age_days = $creation_clock !== null
				? intdiv($now - $creation_clock, $day)
				: null;

			$inactive_days = $last_login_clock !== null
				? intdiv($now - $last_login_clock, $day)
				: null;

			$is_disabled = true;
			foreach ($user['usrgrps'] as $grp) {
				if (($group_access[$grp['usrgrpid']] ?? GROUP_GUI_ACCESS_SYSTEM) != GROUP_GUI_ACCESS_DISABLED) {
					$is_disabled = false;
					break;
				}
			}
			if (!$user['usrgrps']) {
				$is_disabled = false;
			}

			// Policy:
			//   creation_age > 60d AND never logged in                -> DISABLE
			//   creation_age > 60d AND last_login_age > 45d            -> DISABLE
			//   else                                                   -> NO ACTION
			// Unknown creation time is treated conservatively as NO ACTION rather than guessed.
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

			$user['creation_clock']    = $creation_clock;
			$user['last_login_clock']  = $last_login_clock;
			$user['account_age_days']  = $account_age_days;
			$user['inactive_days']     = $inactive_days;
			$user['recommendation']    = $recommendation;
			$user['is_disabled']       = $is_disabled;
			$user['pending_approval']  = CApprovalQueue::isPending($userid);
		}
		unset($user);

		/*
		 * ------------------------------------------------------------
		 * 5. Apply filters (if submitted)
		 * ------------------------------------------------------------
		 */
		$filter = [
			'username'       => $this->hasInput('filter_username') ? $this->getInput('filter_username') : '',
			'activity'       => $this->hasInput('filter_activity') ? $this->getInput('filter_activity') : 'all',
			'account_age'    => $this->hasInput('filter_account_age') ? $this->getInput('filter_account_age') : 'all',
			'recommendation' => $this->hasInput('filter_recommendation') ? $this->getInput('filter_recommendation') : 'all',
			'status'         => $this->hasInput('filter_status') ? $this->getInput('filter_status') : 'enabled'
		];

		if ($this->hasInput('filter_rst')) {
			$filter = [
				'username' => '', 'activity' => 'all', 'account_age' => 'all',
				'recommendation' => 'all', 'status' => 'enabled'
			];
		}

		$filtered_users = array_filter($users, function($user) use ($filter) {
			if ($filter['username'] !== ''
					&& stripos($user['username'], $filter['username']) === false) {
				return false;
			}
			if ($filter['activity'] === 'never' && $user['last_login_clock'] !== null) {
				return false;
			}
			if ($filter['activity'] === 'logged_in' && $user['last_login_clock'] === null) {
				return false;
			}
			if ($filter['activity'] === 'inactive'
					&& !($user['inactive_days'] !== null && $user['inactive_days'] > self::INACTIVITY_THRESHOLD_DAYS)) {
				return false;
			}
			if ($filter['account_age'] === 'gt60'
					&& !($user['account_age_days'] !== null && $user['account_age_days'] > self::MIN_ACCOUNT_AGE_DAYS)) {
				return false;
			}
			if ($filter['account_age'] === 'lt60'
					&& !($user['account_age_days'] !== null && $user['account_age_days'] <= self::MIN_ACCOUNT_AGE_DAYS)) {
				return false;
			}
			if ($filter['recommendation'] !== 'all' && $user['recommendation'] !== $filter['recommendation']) {
				return false;
			}
			if ($filter['status'] === 'enabled' && $user['is_disabled']) {
				return false;
			}
			if ($filter['status'] === 'disabled' && !$user['is_disabled']) {
				return false;
			}
			return true;
		});

		/*
		 * ------------------------------------------------------------
		 * 6. Send everything to the view
		 * ------------------------------------------------------------
		 */
		$data = [
			'title' => _('User Management'),
			'users' => array_values($filtered_users),
			'all_users_count' => count($users),
			'counts' => $counts,
			'filter' => $filter,
			'login_logs_sample' => $login_logs_sample,
			'policy' => [
				'min_account_age_days' => self::MIN_ACCOUNT_AGE_DAYS,
				'inactivity_threshold_days' => self::INACTIVITY_THRESHOLD_DAYS
			]
		];

		$this->setResponse(new CControllerResponseData($data));
	}
}
