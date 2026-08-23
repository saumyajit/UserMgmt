<?php

namespace Modules\UserMgmt\Actions;

use API;
use CController;

class UserPolicyExecute extends CController {

	const APPROVAL_QUEUE_FILE = __DIR__ . '/../data/approval_queue.json';

	public function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'userids' => 'required|array_id',
			'comment' => 'string',
			'mode' => 'in immediate,flag'
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

	private static function loadQueue(): array {
		if (!is_file(self::APPROVAL_QUEUE_FILE)) {
			return [];
		}
		$raw = @file_get_contents(self::APPROVAL_QUEUE_FILE);
		$decoded = $raw !== false ? json_decode($raw, true) : null;
		return is_array($decoded) ? $decoded : [];
	}

	private static function saveQueue(array $queue): bool {
		$dir = dirname(self::APPROVAL_QUEUE_FILE);
		if (!is_dir($dir)) {
			@mkdir($dir, 0755, true);
		}
		return @file_put_contents(self::APPROVAL_QUEUE_FILE, json_encode($queue, JSON_PRETTY_PRINT)) !== false;
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

		if (!$userids) {
			$this->respondJson(false, _('No users selected.'));
		}

		if ($mode === 'flag') {
			$queue = self::loadQueue();
			$now = time();
			$actor = $this->getUserType() !== null ? \CWebUser::$data['username'] ?? 'unknown' : 'unknown';

			foreach ($userids as $userid) {
				$queue[] = [
					'userid' => (string) $userid,
					'status' => 'pending',
					'comment' => $comment,
					'flagged_by' => $actor,
					'flagged_at' => $now
				];
			}

			if (!self::saveQueue($queue)) {
				$this->respondJson(false, _('Failed to write approval queue.'));
			}

			$this->respondJson(true, _('Users flagged for approval.'), ['count' => count($userids)]);
		}

		// mode === 'immediate': disable now, comment required as Request No./justification
		if ($comment === '') {
			$this->respondJson(false, _('A Request No. / comment is required to disable users.'));
		}

		try {
			$disabled_usrgrpid = self::getOrCreateDisabledUsrgrp();

			foreach ($userids as $userid) {
				$user = API::User()->get([
					'output' => ['userid'],
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
			}
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
				$entry['resolved_at'] = time();
			}
		}
		unset($entry);
		self::saveQueue($queue);

		$this->respondJson(true, _('Users disabled.'), ['count' => count($userids)]);
	}
}
