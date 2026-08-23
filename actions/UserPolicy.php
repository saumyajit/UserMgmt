<?php

namespace Modules\UserMgmt\Actions;

use API;
use CController;
use CControllerResponseData;

class UserPolicy extends CController {

	private const ACCOUNT_AGE_THRESHOLD_DAYS = 60;
	private const INACTIVITY_THRESHOLD_DAYS = 45;


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

		$current_time = time();

		$account_age_threshold = self::ACCOUNT_AGE_THRESHOLD_DAYS;
		$inactivity_threshold = self::INACTIVITY_THRESHOLD_DAYS;


		/*
		 * ========================================================
		 * 1. GET ENABLED USERS
		 * ========================================================
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
			'filter' => [
				'status' => 0
			],
			'sortfield' => 'username',
			'sortorder' => ZBX_SORT_UP
		]);


		/*
		 * ========================================================
		 * 2. GET USER CREATION AUDIT RECORDS
		 * ========================================================
		 */

		$user_creation_logs = API::AuditLog()->get([
			'output' => [
				'auditid',
				'userid',
				'username',
				'clock',
				'action',
				'resourcetype',
				'resourceid',
				'resourcename',
				'ip'
			],
			'filter' => [
				'action' => 0,
				'resourcetype' => 0
			],
			'sortfield' => 'clock',
			'sortorder' => 'DESC'
		]);


		/*
		 * ========================================================
		 * 3. BUILD CREATION TIME LOOKUP
		 * ========================================================
		 *
		 * userid => creation timestamp
		 */

		$creation_times = [];

		foreach ($user_creation_logs as $audit) {

			if (!isset($audit['resourceid']) || !isset($audit['clock'])) {
				continue;
			}

			$userid = (string) $audit['resourceid'];

			if (!isset($creation_times[$userid])) {
				$creation_times[$userid] = (int) $audit['clock'];
			}
		}


		/*
		 * ========================================================
		 * 4. CLASSIFY USERS BY ACCOUNT AGE
		 * ========================================================
		 */

		$users_over_threshold = [];
		$users_under_threshold = [];
		$users_creation_unknown = [];


		foreach ($users as $user) {

			$userid = (string) $user['userid'];

			$creation_clock = $creation_times[$userid] ?? null;


			/*
			 * Creation timestamp unavailable.
			 */

			if ($creation_clock === null) {

				$user['creation_clock'] = null;
				$user['account_age_days'] = null;
				$user['evaluation'] = 'creation_unknown';
				$user['recommendation'] = 'no_action';

				$users_creation_unknown[] = $user;

				continue;
			}


			/*
			 * Calculate account age.
			 */

			$account_age_seconds = $current_time - $creation_clock;

			if ($account_age_seconds < 0) {
				$account_age_seconds = 0;
			}

			$account_age_days = floor(
				$account_age_seconds / 86400
			);


			$user['creation_clock'] = $creation_clock;
			$user['account_age_days'] = $account_age_days;


			if ($account_age_days >= $account_age_threshold) {

				$user['evaluation'] = 'activity_check_required';
				$user['recommendation'] = 'pending';

				$users_over_threshold[] = $user;
			}
			else {

				$user['evaluation'] = 'new_account';
				$user['recommendation'] = 'no_action';

				$users_under_threshold[] = $user;
			}
		}


		/*
		 * ========================================================
		 * 5. BUILD CANDIDATE USER ID LIST
		 * ========================================================
		 */

		$candidate_userids = [];

		foreach ($users_over_threshold as $user) {
			$candidate_userids[] = (string) $user['userid'];
		}


		/*
		 * ========================================================
		 * 6. GET SUCCESSFUL LOGIN AUDIT RECORDS
		 * ========================================================
		 *
		 * action       = 8 -> Login
		 * resourcetype = 0 -> User
		 *
		 * We retrieve login events and correlate them against
		 * candidate user IDs.
		 *
		 * We deliberately do NOT disable anything here.
		 */

		$login_logs = [];


		if ($candidate_userids) {

			$login_logs = API::AuditLog()->get([
				'output' => [
					'auditid',
					'userid',
					'username',
					'clock',
					'action',
					'resourcetype',
					'resourceid',
					'resourcename',
					'ip'
				],
				'filter' => [
					'action' => 8,
					'resourcetype' => 0
				],
				'sortfield' => 'clock',
				'sortorder' => 'DESC'
			]);
		}


		/*
		 * ========================================================
		 * 7. BUILD LAST LOGIN LOOKUP
		 * ========================================================
		 *
		 * userid => latest login timestamp
		 *
		 * We only retain records belonging to users who are
		 * candidates (>60 days).
		 */

		$last_login_times = [];


		foreach ($login_logs as $login) {

			if (!isset($login['userid']) || !isset($login['clock'])) {
				continue;
			}

			$userid = (string) $login['userid'];


			/*
			 * Ignore login activity belonging to users that
			 * don't need evaluation.
			 */

			if (!in_array($userid, $candidate_userids, true)) {
				continue;
			}


			/*
			 * Since the query is sorted DESC, the first login
			 * record we encounter is the latest login.
			 */

			if (!isset($last_login_times[$userid])) {
				$last_login_times[$userid] = (int) $login['clock'];
			}
		}


		/*
		 * ========================================================
		 * 8. APPLY LOGIN POLICY
		 * ========================================================
		 */

		$evaluated_users = [];

		$recommended_disable = [];

		$active_users = [];

		$never_logged_in = [];

		$inactive_users = [];


		foreach ($users_over_threshold as $user) {

			$userid = (string) $user['userid'];

			$last_login_clock = $last_login_times[$userid] ?? null;


			/*
			 * ----------------------------------------------------
			 * NEVER LOGGED IN
			 * ----------------------------------------------------
			 */

			if ($last_login_clock === null) {

				$user['last_login_clock'] = null;
				$user['inactive_days'] = null;

				$user['evaluation'] = 'never_logged_in';

				$user['recommendation'] = 'disable';

				$never_logged_in[] = $user;

				$recommended_disable[] = $user;

				$evaluated_users[] = $user;

				continue;
			}


			/*
			 * ----------------------------------------------------
			 * LAST LOGIN EXISTS
			 * ----------------------------------------------------
			 */

			$inactive_seconds =
				$current_time - $last_login_clock;


			if ($inactive_seconds < 0) {
				$inactive_seconds = 0;
			}


			$inactive_days = floor(
				$inactive_seconds / 86400
			);


			$user['last_login_clock'] = $last_login_clock;
			$user['inactive_days'] = $inactive_days;


			/*
			 * ----------------------------------------------------
			 * INACTIVE > 45 DAYS
			 * ----------------------------------------------------
			 */

			if ($inactive_days > $inactivity_threshold) {

				$user['evaluation'] = 'inactive';

				$user['recommendation'] = 'disable';

				$inactive_users[] = $user;

				$recommended_disable[] = $user;
			}


			/*
			 * ----------------------------------------------------
			 * ACTIVE
			 * ----------------------------------------------------
			 */

			else {

				$user['evaluation'] = 'active';

				$user['recommendation'] = 'no_action';

				$active_users[] = $user;
			}


			$evaluated_users[] = $user;
		}


		/*
		 * ========================================================
		 * 9. SUMMARY
		 * ========================================================
		 */

		$summary = [
			'total_enabled_users' => count($users),

			'users_over_threshold' =>
				count($users_over_threshold),

			'users_under_threshold' =>
				count($users_under_threshold),

			'users_creation_unknown' =>
				count($users_creation_unknown),

			'never_logged_in' =>
				count($never_logged_in),

			'inactive_users' =>
				count($inactive_users),

			'active_users' =>
				count($active_users),

			'recommended_disable' =>
				count($recommended_disable)
		];


		/*
		 * ========================================================
		 * 10. RESPONSE DATA
		 * ========================================================
		 */

		$data = [
			'title' => _('User Management'),

			'users' => $users,

			'candidate_users' => $users_over_threshold,

			'evaluated_users' => $evaluated_users,

			'users_under_threshold' => $users_under_threshold,

			'users_creation_unknown' => $users_creation_unknown,

			'never_logged_in' => $never_logged_in,

			'inactive_users' => $inactive_users,

			'active_users' => $active_users,

			'recommended_disable' => $recommended_disable,

			'creation_times' => $creation_times,

			'last_login_times' => $last_login_times,

			'creation_logs' => $user_creation_logs,

			'login_logs' => $login_logs,

			'candidate_userids' => $candidate_userids,

			'account_age_threshold' =>
				$account_age_threshold,

			'inactivity_threshold' =>
				$inactivity_threshold,

			'current_time' =>
				$current_time,

			'summary' =>
				$summary
		];


		$this->setResponse(
			new CControllerResponseData($data)
		);
	}
}
