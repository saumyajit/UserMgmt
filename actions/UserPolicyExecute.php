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
			'mode' => 'in flag,approve,reject,disable'
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
	 * Resolves the current target user identity from the stable Zabbix userid.
	 *
	 * This intentionally does not rely on name/surname saved in the approval
	 * queue because older queue records can contain empty values.
	 */
	private static function getUserIdentity(string $userid): array {
		$users = API::User()->get([
			'output' => ['userid', 'username', 'name', 'surname'],
			'userids' => [$userid]
		]);

		if (!$users) {
			return [
				'userid' => $userid,
				'username' => '',
				'name' => '',
				'surname' => ''
			];
		}

		return [
			'userid' => (string) $users[0]['userid'],
			'username' => (string) ($users[0]['username'] ?? ''),
			'name' => (string) ($users[0]['name'] ?? ''),
			'surname' => (string) ($users[0]['surname'] ?? '')
		];
	}

	/**
	 * Appends one complete activity-log record.
	 *
	 * Target identity is supplied by each action call site. Actor identity is
	 * captured from the currently authenticated Zabbix frontend user.
	 */
	private static function logActivity(
		string $action,
		string $userid,
		string $username,
		string $comment,
		string $actor,
		array $extra = []
	): void {
		$log = self::loadJsonFile(self::ACTIVITY_LOG_FILE);
		$actor_data = \CWebUser::$data ?? [];

		$log[] = array_merge([
			'action' => $action,
			'userid' => $userid,
			'username' => $username,
			'comment' => $comment,
			'actor' => $actor,
			'actor_name' => trim((string) ($actor_data['name'] ?? '')),
			'actor_surname' => trim((string) ($actor_data['surname'] ?? '')),
			'clock' => time()
		], $extra);

		self::saveJsonFile(self::ACTIVITY_LOG_FILE, $log);
	}

	private static function currentActor(): string {
		return \CWebUser::$data['username'] ?? 'unknown';
	}

	private static function loadApprovers(): array {
		$config = self::loadJsonFile(self::CONFIG_FILE);

		return isset($config['approvers']) && is_array($config['approvers'])
			? $config['approvers']
			: [];
	}

	/**
	 * If an allowlist is configured, only listed Super Admins may approve
	 * or reject. An empty allowlist means any Super Admin may do so.
	 */
	private function enforceApproverPermission(string $actor): void {
		$approvers = self::loadApprovers();

		if ($approvers && !in_array($actor, $approvers, true)) {
			$this->respondJson(
				false,
				_('You are not on the approver list for this module.')
			);
		}
	}

	/**
	 * Zabbix disables a user when any assigned user group has
	 * users_status = GROUP_STATUS_DISABLED. This module adds users to a
	 * dedicated disabled group without replacing existing memberships.
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
	 * Disables every requested user and writes one complete audit entry
	 * per target user.
	 */
	private static function disableUsers(
		array $userids,
		string $comment,
		string $actor,
		string $action = 'disable'
	): void {
		$disabled_usrgrpid = self::getOrCreateDisabledUsrgrp();

		foreach ($userids as $userid) {
			$user = API::User()->get([
				'output' => ['userid', 'username', 'name', 'surname'],
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

			self::logActivity(
				$action,
				(string) $user[0]['userid'],
				(string) $user[0]['username'],
				$comment,
				$actor,
				[
					'name' => (string) ($user[0]['name'] ?? ''),
					'surname' => (string) ($user[0]['surname'] ?? '')
				]
			);
		}
	}

	private function respondJson(
		bool $success,
		string $message,
		array $extra = []
	): void {
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
				$user = self::getUserIdentity((string) $userid);

				$queue[] = [
					'userid' => $user['userid'],
					'username' => $user['username'],
					'name' => $user['name'],
					'surname' => $user['surname'],
					'status' => 'pending',
					'comment' => $comment,
					'flagged_by' => $actor,
					'flagged_at' => $now
				];

				self::logActivity(
					'flag',
					$user['userid'],
					$user['username'],
					$comment,
					$actor,
					[
						'name' => $user['name'],
						'surname' => $user['surname']
					]
				);
			}

			if (!self::saveQueue($queue)) {
				$this->respondJson(false, _('Failed to write approval queue.'));
			}

			$this->respondJson(
				true,
				_('Users flagged for approval.'),
				['count' => count($userids)]
			);
		}

		if ($mode === 'approve') {
			$this->enforceApproverPermission($actor);

			$queue_index = $this->getInput('queue_index', null);

			if ($queue_index === null) {
				$this->respondJson(false, _('Missing approval queue reference.'));
			}

			$queue = self::loadQueue();

			if (
				!isset($queue[$queue_index]) ||
				($queue[$queue_index]['status'] ?? '') !== 'pending'
			) {
				$this->respondJson(false, _('This request is no longer pending.'));
			}

			$entry = $queue[$queue_index];

			if (($entry['flagged_by'] ?? null) === $actor) {
				$this->respondJson(
					false,
					_('You cannot approve a request you flagged yourself. Another approver must review it.')
				);
			}

			/*
			 * Resolve target details now, by Zabbix userid, instead of using
			 * potentially blank name/surname stored in approval_queue.json.
			 */
			$user = self::getUserIdentity((string) $entry['userid']);

			$queue[$queue_index]['status'] = 'approved';
			$queue[$queue_index]['approved_by'] = $actor;
			$queue[$queue_index]['approved_at'] = time();
			$queue[$queue_index]['approver_comment'] = $comment;

			/*
			 * Refresh queue identity fields too, so later reject/disable
			 * workflows do not propagate old blank queue values.
			 */
			$queue[$queue_index]['username'] = $user['username'];
			$queue[$queue_index]['name'] = $user['name'];
			$queue[$queue_index]['surname'] = $user['surname'];

			if (!self::saveQueue($queue)) {
				$this->respondJson(false, _('Failed to update approval queue.'));
			}

			self::logActivity(
				'approve',
				$user['userid'],
				$user['username'],
				$comment,
				$actor,
				[
					'name' => $user['name'],
					'surname' => $user['surname']
				]
			);

			$this->respondJson(
				true,
				_('Request approved. The user can now be disabled.')
			);
		}

		if ($mode === 'disable') {
			$queue_index = $this->getInput('queue_index', null);

			if ($queue_index === null) {
				$this->respondJson(false, _('Missing approval queue reference.'));
			}

			$queue = self::loadQueue();

			if (
				!isset($queue[$queue_index]) ||
				($queue[$queue_index]['status'] ?? '') !== 'approved'
			) {
				$this->respondJson(false, _('This request has not been approved yet.'));
			}

			$entry = $queue[$queue_index];

			if (($entry['approved_by'] ?? null) === $actor) {
				$this->respondJson(
					false,
					_('You cannot disable a user you approved yourself. Another Super Admin must complete this step.')
				);
			}

			$disable_comment = $comment;
			$final_comment = $disable_comment !== ''
				? $disable_comment . (
					!empty($entry['comment'])
						? ' | Request: ' . $entry['comment']
						: ''
				)
				: ($entry['comment'] ?? '');

			try {
				self::disableUsers(
					[(string) $entry['userid']],
					$final_comment,
					$actor,
					'disable'
				);
			}
			catch (\Exception $e) {
				$this->respondJson(false, $e->getMessage());
			}

			$queue[$queue_index]['status'] = 'disabled';
			$queue[$queue_index]['disabled_by'] = $actor;
			$queue[$queue_index]['disabled_at'] = time();
			$queue[$queue_index]['disable_comment'] = $disable_comment;

			if (!self::saveQueue($queue)) {
				$this->respondJson(false, _('Failed to update approval queue.'));
			}

			$this->respondJson(true, _('User disabled.'));
		}

		if ($mode === 'reject') {
			$this->enforceApproverPermission($actor);

			$queue_index = $this->getInput('queue_index', null);

			if ($queue_index === null) {
				$this->respondJson(false, _('Missing approval queue reference.'));
			}

			$queue = self::loadQueue();

			if (
				!isset($queue[$queue_index]) ||
				($queue[$queue_index]['status'] ?? '') !== 'pending'
			) {
				$this->respondJson(false, _('This request is no longer pending.'));
			}

			$entry = $queue[$queue_index];

			/*
			 * Resolve target details now, by Zabbix userid. This prevents a
			 * reject entry from inheriting empty target names from a legacy
			 * approval queue record.
			 */
			$user = self::getUserIdentity((string) $entry['userid']);

			$queue[$queue_index]['status'] = 'rejected';
			$queue[$queue_index]['resolved_by'] = $actor;
			$queue[$queue_index]['resolved_at'] = time();
			$queue[$queue_index]['reject_reason'] = $comment;
			$queue[$queue_index]['username'] = $user['username'];
			$queue[$queue_index]['name'] = $user['name'];
			$queue[$queue_index]['surname'] = $user['surname'];

			if (!self::saveQueue($queue)) {
				$this->respondJson(false, _('Failed to update approval queue.'));
			}

			self::logActivity(
				'reject',
				$user['userid'],
				$user['username'],
				$comment,
				$actor,
				[
					'name' => $user['name'],
					'surname' => $user['surname']
				]
			);

			$this->respondJson(true, _('Request rejected.'));
		}

		$this->respondJson(false, _('Unsupported action.'));
	}
}
