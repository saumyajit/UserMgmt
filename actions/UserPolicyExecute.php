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
		/*
		 * userids is required only for flag. Approve, reject and
		 * disable_approved address exactly one queue entry by queue_index;
		 * requiring userids for those modes caused the "Invalid input."
		 * error shown when clicking Disable in Ready to Disable.
		 */
		$fields = [
			'userids' => 'array_id',
			'queue_index' => 'int32',
			'comment' => 'string',
			'mode' => 'in flag,approve,reject,disable_approved'
		];

		$ret = $this->validateInput($fields);

		if (!$ret) {
			$this->respondJson(false, _('Invalid input.'));
		}

		$mode = $this->getInput('mode', '');
		$userids = $this->getInput('userids', []);
		$queue_index = $this->getInput('queue_index', null);

		if ($mode === 'flag' && !$userids) {
			$this->respondJson(false, _('No users selected.'));
		}

		if (in_array($mode, ['approve', 'reject', 'disable_approved'], true) && $queue_index === null) {
			$this->respondJson(false, _('Missing approval queue reference.'));
		}

		return true;
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

	private function enforceApproverPermission(string $actor): void {
		$approvers = self::loadApprovers();

		if ($approvers && !in_array($actor, $approvers, true)) {
			$this->respondJson(false, _('You are not on the approver list for this module.'));
		}
	}

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

				API::User()->update([
					'userid' => $userid,
					'usrgrps' => array_map(function ($id) {
						return ['usrgrpid' => $id];
					}, $usrgrpids)
				]);
			}

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
		$mode = $this->getInput('mode', 'flag');
		$actor = self::currentActor();

		if ($mode === 'flag') {
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
			$queue = self::loadQueue();

			if (!isset($queue[$queue_index]) || $queue[$queue_index]['status'] !== 'pending') {
				$this->respondJson(false, _('This request is no longer pending.'));
			}

			$queue[$queue_index]['status'] = 'approved';
			$queue[$queue_index]['approved_by'] = $actor;
			$queue[$queue_index]['approved_at'] = time();
			$queue[$queue_index]['approver_comment'] = $comment;
			self::saveQueue($queue);

			self::logActivity('approve', (string) $queue[$queue_index]['userid'], $queue[$queue_index]['username'] ?? '', $comment, $actor);

			$this->respondJson(true, _('Request approved. A different Super Admin must now disable the user.'));
		}

		if ($mode === 'reject') {
			$this->enforceApproverPermission($actor);
			$queue_index = $this->getInput('queue_index', null);
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

		if ($mode === 'disable_approved') {
			$queue_index = $this->getInput('queue_index', null);
			$queue = self::loadQueue();

			if (!isset($queue[$queue_index]) || $queue[$queue_index]['status'] !== 'approved') {
				$this->respondJson(false, _('This request is not in an approved state (it may be pending, rejected, or already disabled).'));
			}

			$entry = $queue[$queue_index];

			if (($entry['approved_by'] ?? '') === $actor) {
				$this->respondJson(false, _('You approved this request — a different Super Admin must perform the disable.'));
			}

			$final_comment = $comment !== ''
				? $comment
				: ($entry['approver_comment'] ?? $entry['comment'] ?? '');

			try {
				self::disableUsers([$entry['userid']], $final_comment, $actor, 'disable');
			}
			catch (\Exception $e) {
				$this->respondJson(false, $e->getMessage());
			}

			$queue[$queue_index]['status'] = 'disabled';
			$queue[$queue_index]['disabled_by'] = $actor;
			$queue[$queue_index]['disabled_at'] = time();
			self::saveQueue($queue);

			$this->respondJson(true, _('User disabled.'));
		}

		$this->respondJson(false, _('Unknown request.'));
	}
}
