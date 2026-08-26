<?php

namespace Modules\UserMgmt\Actions;

use API;
use CController;

class UserPolicyExecute extends CController {

	const APPROVAL_QUEUE_FILE = __DIR__ . '/../data/approval_queue.json';
	const ACTIVITY_LOG_FILE = __DIR__ . '/../data/activity_log.json';
	const CONFIG_FILE = __DIR__ . '/../data/policy_config.json';

	public function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'userids' => 'array_id',
			'queue_index' => 'int32',
			'comment' => 'string',
			'mode' => 'in immediate,flag,approve,reject'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->respondJson(false, _('Invalid input.'));
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() >= USER_TYPE_SUPER_ADMIN;
	}

	private static function loadJsonFile(string $file): array {
		if (!is_file($file)) {
			return [];
		}
		$raw = @file_get_contents($file);
		$decoded = $raw !== false ? json_decode($raw, true) : null;
		return is_array($decoded) ? $decoded : [];
	}

	private static function saveJsonFile(string $file, array $data): bool {
		$dir = dirname($file);
		if (!is_dir($dir)) {
			@mkdir($dir, 0755, true);
		}
		return @file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT)) !== false;
	}

	private static function loadQueue(): array {
		return self::loadJsonFile(self::APPROVAL_QUEUE_FILE);
	}

	private static function saveQueue(array $queue): bool {
		return self::saveJsonFile(self::APPROVAL_QUEUE_FILE, $queue);
	}

	/**
	 * Single append-only trail of every comment/action taken through this module —
	 * flag, disable, approve, reject — independent of Zabbix's own audit log (which
	 * has no room for our comments). UserPolicy.php reads this to show the "Comment"
	 * column and a scrollable Activity Log panel.
	 */
	private static function logActivity(string $action, string $userid, string $username, string $comment, string $actor, array $extra = []): void {
		$log = self::loadJsonFile(self::ACTIVITY_LOG_FILE);
		$log[] = array_merge([
			'action' => $action,
			'userid' => $userid,
			'username' => $username,
			'comment' => $comment,
			'actor' => $actor,
			'clock' => time()
		], $extra);
		self::saveJsonFile(self::ACTIVITY_LOG_FILE, $log);
	}

	private static function currentActor(): string {
		return \CWebUser::$data['username'] ?? 'unknown';
	}

	private static function loadApprovers(): array {
		$config = self::loadJsonFile(self::CONFIG_FILE);
		return isset($config['approvers']) && is_array($config['approvers']) ? $config['approvers'] : [];
	}

	/**
	 * If an approver allowlist has been configured, only usernames on it may approve
	 * or reject. An empty list means "any Super Admin" (the original behaviour).
	 */
	private function enforceApproverPermission(string $actor): void {
		$approvers = self::loadApprovers();

		if ($approvers && !in_array($actor, $approvers, true)) {
			$this->respondJson(false, _('You are not on the approver list for this module.'));
		}
	}

	/**
	 * Zabbix has no per-user "disabled" flag reachable via user.update. A user is
	 * disabled when ANY of their user groups has users_status = GROUP_STATUS_DISABLED (1).
	 * We reuse (or create once) a dedicated group for that, and ADD users to it rather
	 * than replacing their existing group memberships, so re-enabling later (removing
	 * them from this one group) doesn't lose their original group/permission setup.
	 */
	private static function getOrCreateDisabledUsrgrp(): string {
		$name = 'Disabled by User Mgmt policy';

		$existing = API::UserGroup()->get([
			'output' => ['usrgrpid'],
			'filter' => ['name' => $name]
		]);

		if ($existing) {
			return $existing[0]['usrgrpid'];
		}

		$created = API::UserGroup()->create([
			'name' => $name,
			'users_status' => GROUP_STATUS_DISABLED
		]);

		return $created['usrgrpids'][0];
	}

	/**
	 * Disables the given users (via group membership, see above) and logs one
	 * activity entry per user.
	 */
	private static function disableUsers(array $userids, string $comment, string $actor, string $action = 'disable'): void {
		$disabled_usrgrpid = self::getOrCreateDisabledUsrgrp();

		foreach ($userids as $userid) {
			$user = API::User()->get([
				'output' => ['userid', 'username'],
				'selectUsrgrps' => ['usrgrpid'],
				'userids' => [$userid]
			]);

			if (!$user) {
				continue;
			}

			$usrgrpids = array_column($user[0]['usrgrps'], 'usrgrpid');

			if (!in_array($disabled_usrgrpid, $usrgrpids, true)) {
				$usrgrpids[] = $disabled_usrgrpid;
			}

			API::User()->update([
				'userid' => $userid,
				'usrgrps' => array_map(function ($id) {
					return ['usrgrpid' => $id];
				}, $usrgrpids)
			]);

			self::logActivity($action, (string) $userid, $user[0]['username'], $comment, $actor);
		}
	}

	private function respondJson(bool $success, string $message, array $extra = []): void {
		header('Content-Type: application/json');
		echo json_encode(array_merge([
			'success' => $success,
			'message' => $message
		], $extra));
		session_write_close();
		exit;
	}

	protected function doAction(): void {
		$userids = $this->getInput('userids', []);
		$comment = trim($this->getInput('comment', ''));
		$mode = $this->getInput('mode', 'immediate');
		$actor = self::currentActor();

		if ($mode === 'flag') {
			if (!$userids) {
				$this->respondJson(false, _('No users selected.'));
			}

			$queue = self::loadQueue();
			$now = time();

			foreach ($userids as $userid) {
				$user = API::User()->get(['output' => ['username'], 'userids' => [$userid]]);
				$username = $user ? $user[0]['username'] : '';

				$queue[] = [
					'userid' => (string) $userid,
					'username' => $username,
					'status' => 'pending',
					'comment' => $comment,
					'flagged_by' => $actor,
					'flagged_at' => $now
				];

				self::logActivity('flag', (string) $userid, $username, $comment, $actor);
			}

			if (!self::saveQueue($queue)) {
				$this->respondJson(false, _('Failed to write approval queue.'));
			}

			$this->respondJson(true, _('Users flagged for approval.'), ['count' => count($userids)]);
		}

		if ($mode === 'approve') {
			$this->enforceApproverPermission($actor);

			$queue_index = $this->getInput('queue_index', null);

			if ($queue_index === null) {
				$this->respondJson(false, _('Missing approval queue reference.'));
			}

			$queue = self::loadQueue();

			if (!isset($queue[$queue_index]) || $queue[$queue_index]['status'] !== 'pending') {
				$this->respondJson(false, _('This request is no longer pending.'));
			}

			$entry = $queue[$queue_index];
			$approver_comment = $comment;
			$final_comment = $approver_comment !== ''
				? $approver_comment . ($entry['comment'] ? ' | Request: ' . $entry['comment'] : '')
				: ($entry['comment'] ?? '');

			try {
				self::disableUsers([$entry['userid']], $final_comment, $actor, 'approve');
			}
			catch (\Exception $e) {
				$this->respondJson(false, $e->getMessage());
			}

			$queue[$queue_index]['status'] = 'disabled';
			$queue[$queue_index]['resolved_by'] = $actor;
			$queue[$queue_index]['resolved_at'] = time();
			$queue[$queue_index]['approver_comment'] = $approver_comment;
			self::saveQueue($queue);

			$this->respondJson(true, _('User disabled.'));
		}

		if ($mode === 'reject') {
			$this->enforceApproverPermission($actor);

			$queue_index = $this->getInput('queue_index', null);

			if ($queue_index === null) {
				$this->respondJson(false, _('Missing approval queue reference.'));
			}

			$queue = self::loadQueue();

			if (!isset($queue[$queue_index]) || $queue[$queue_index]['status'] !== 'pending') {
				$this->respondJson(false, _('This request is no longer pending.'));
			}

			$queue[$queue_index]['status'] = 'rejected';
			$queue[$queue_index]['resolved_by'] = $actor;
			$queue[$queue_index]['resolved_at'] = time();
			$queue[$queue_index]['reject_reason'] = $comment;
			self::saveQueue($queue);

			self::logActivity('reject', (string) $queue[$queue_index]['userid'], $queue[$queue_index]['username'] ?? '', $comment, $actor);

			$this->respondJson(true, _('Request rejected.'));
		}

		// mode === 'immediate': disable now, comment required as Request No./justification
		if (!$userids) {
			$this->respondJson(false, _('No users selected.'));
		}

		if ($comment === '') {
			$this->respondJson(false, _('A Request No. / comment is required to disable users.'));
		}

		try {
			self::disableUsers($userids, $comment, $actor, 'disable');
		}
		catch (\Exception $e) {
			$this->respondJson(false, $e->getMessage());
		}

		// Mark any matching pending approval entries as resolved.
		$queue = self::loadQueue();
		foreach ($queue as &$entry) {
			if (in_array((string) $entry['userid'], array_map('strval', $userids), true) && $entry['status'] === 'pending') {
				$entry['status'] = 'disabled';
				$entry['resolved_comment'] = $comment;
				$entry['resolved_by'] = $actor;
				$entry['resolved_at'] = time();
			}
		}
		unset($entry);
		self::saveQueue($queue);

		$this->respondJson(true, _('Users disabled.'), ['count' => count($userids)]);
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
