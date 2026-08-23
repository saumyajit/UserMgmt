<?php

namespace Modules\UserMgmt\Actions;

use API;
use CController;
use CControllerResponseData;

class UserPolicy extends CController {

	/*
	 * ------------------------------------------------------------
	 * Module configuration
	 * ------------------------------------------------------------
	 */

	private const ACCOUNT_AGE_THRESHOLD_DAYS = 60;


	/*
	 * ------------------------------------------------------------
	 * Controller initialization
	 * ------------------------------------------------------------
	 */

	public function init(): void {
		$this->disableCsrfValidation();
	}


	/*
	 * ------------------------------------------------------------
	 * Input validation
	 * ------------------------------------------------------------
	 */

	protected function checkInput(): bool {
		return true;
	}


	/*
	 * ------------------------------------------------------------
	 * Permission check
	 *
	 * auditlog.get requires Super Admin privileges.
	 * ------------------------------------------------------------
	 */

	protected function checkPermissions(): bool {
		return $this->getUserType() >= USER_TYPE_SUPER_ADMIN;
	}


	/*
	 * ------------------------------------------------------------
	 * Main action
	 * ------------------------------------------------------------
	 */

	protected function doAction(): void {

		/*
		 * Current server time.
		 */
		$current_time = time();

		/*
		 * Account age policy.
		 */
		$account_age_threshold = self::ACCOUNT_AGE_THRESHOLD_DAYS;


		/*
		 * ========================================================
		 * 1. GET ENABLED USERS
		 * ========================================================
		 *
		 * status = 0
		 * means the user account is enabled.
		 *
		 * Keep this query in the same form that we already
		 * confirmed works in your environment.
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
		 *
		 * action       = 0
		 * resourcetype = 0
		 *
		 * For a user creation event:
		 *
		 * resourceid   = newly created user's userid
		 * resourcename = newly created user's username
		 * userid       = user who performed the creation
		 * clock        = creation timestamp
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
		 * 3. BUILD CREATION-TIME LOOKUP
		 * ========================================================
		 *
		 * Result:
		 *
		 *     userid => creation timestamp
		 *
		 * Example:
		 *
		 *     744 => 1729154939
		 */

		$creation_times = [];

		foreach ($user_creation_logs as $audit) {

			if (!isset($audit['resourceid']) || !isset($audit['clock'])) {
				continue;
			}

			$userid = (string) $audit['resourceid'];

			/*
			 * Because the records are sorted DESC, the first
			 * occurrence is the newest matching creation record.
			 */
			if (!isset($creation_times[$userid])) {
				$creation_times[$userid] = (int) $audit['clock'];
			}
		}


		/*
		 * ========================================================
		 * 4. EVALUATE ACCOUNT AGE
		 * ========================================================
		 *
		 * We deliberately do NOT evaluate login activity yet.
		 *
		 * At this stage:
		 *
		 *     < 60 days
		 *          -> no activity check required
		 *
		 *     >= 60 days
		 *          -> candidate for login evaluation
		 *
		 *     creation time unavailable
		 *          -> no automatic action
		 */

		$all_users = [];

		$users_over_threshold = [];

		$users_under_threshold = [];

		$users_creation_unknown = [];


		foreach ($users as $user) {

			$userid = (string) $user['userid'];

			$creation_clock = $creation_times[$userid] ?? null;


			/*
			 * ----------------------------------------------------
			 * Creation timestamp not available
			 * ----------------------------------------------------
			 */

			if ($creation_clock === null) {

				$user['creation_clock'] = null;
				$user['account_age_days'] = null;
				$user['evaluation'] = 'creation_unknown';
				$user['recommendation'] = 'no_action';

				$users_creation_unknown[] = $user;
				$all_users[] = $user;

				continue;
			}


			/*
			 * ----------------------------------------------------
			 * Calculate account age
			 * ----------------------------------------------------
			 */

			$account_age_seconds = $current_time - $creation_clock;

			/*
			 * Protect against an invalid/future timestamp.
			 */
			if ($account_age_seconds < 0) {
				$account_age_seconds = 0;
			}

			$account_age_days = floor(
				$account_age_seconds / 86400
			);


			$user['creation_clock'] = $creation_clock;
			$user['account_age_days'] = $account_age_days;


			/*
			 * ----------------------------------------------------
			 * Account >= 60 days
			 * ----------------------------------------------------
			 */

			if ($account_age_days >= $account_age_threshold) {

				$user['evaluation'] = 'activity_check_required';

				/*
				 * We do NOT recommend disable yet.
				 *
				 * Login history must be checked first.
				 */
				$user['recommendation'] = 'pending_activity_check';

				$users_over_threshold[] = $user;
			}


			/*
			 * ----------------------------------------------------
			 * Account < 60 days
			 * ----------------------------------------------------
			 */

			else {

				$user['evaluation'] = 'new_account';

				$user['recommendation'] = 'no_action';

				$users_under_threshold[] = $user;
			}


			$all_users[] = $user;
		}


		/*
		 * ========================================================
		 * 5. SUMMARY
		 * ========================================================
		 */

		$summary = [
			'total_enabled_users' => count($users),

			'users_over_threshold' => count($users_over_threshold),

			'users_under_threshold' => count($users_under_threshold),

			'users_creation_unknown' => count($users_creation_unknown),

			/*
			 * Login evaluation has NOT happened yet.
			 */
			'recommended_disable' => 0,

			'pending_activity_check' => count($users_over_threshold)
		];


		/*
		 * ========================================================
		 * 6. RESPONSE DATA
		 * ========================================================
		 */

		$data = [
			'title' => _('User Management'),

			'users' => $all_users,

			'candidate_users' => $users_over_threshold,

			'users_under_threshold' => $users_under_threshold,

			'users_creation_unknown' => $users_creation_unknown,

			'creation_times' => $creation_times,

			'creation_logs' => $user_creation_logs,

			'account_age_threshold' => $account_age_threshold,

			'current_time' => $current_time,

			'summary' => $summary
		];


		/*
		 * Send response to view.
		 */

		$this->setResponse(
			new CControllerResponseData($data)
		);
	}
}
