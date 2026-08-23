<?php

namespace Modules\UserMgmt\Actions;

use API;
use CController;
use CControllerResponseData;

class UserPolicyExecute extends CController {

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

	private static function saveQueue(array $data): void {
		$dir = dirname(self::queueFile());
		if (!is_dir($dir)) {
			mkdir($dir, 0775, true);
		}
		file_put_contents(self::queueFile(), json_encode($data, JSON_PRETTY_PRINT));
	}

	private static function addQueueEntry(string $userid, string $username, string $requested_by,
			string $request_no, string $comment, string $status): void {
		$data = self::loadQueue();
		$data[] = [
			'id' => uniqid('appr_', true),
			'userid' => $userid,
			'username' => $username,
			'requested_by' => $requested_by,
			'request_no' => $request_no,
			'comment' => $comment,
			'status' => $status,
			'created' => time()
		];
		self::saveQueue($data);
	}

	public function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		return $this->validateInput([
			'userids'    => 'required|array_db users.userid',
			'mode'       => 'required|in disable_now,flag_approval',
			'request_no' => 'string',
			'comment'    => 'string'
		]);
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() >= USER_TYPE_SUPER_ADMIN;
	}

	protected function doAction(): void {
		$mode = $this->getInput('mode');
		$userids = $this->getInput('userids');
		$request_no = $this->getInput('request_no', '');
		$comment = $this->getInput('comment', '');
		$requested_by = \CWebUser::$data['username'] ?? (string) \CWebUser::$data['userid'];

		if ($mode === 'disable_now' && trim($comment) === '') {
			$this->setResponse(new CControllerResponseData([
				'main_block' => json_encode(['success' => false, 'error' => _('A comment is required to disable directly.')])
			]));
			return;
		}

		$users = API::User()->get([
			'output' => ['userid', 'username'],
			'selectUsrgrps' => ['usrgrpid'],
			'userids' => $userids
		]);

		$processed = [];

		foreach ($users as $user) {
			if ($mode === 'disable_now') {
				$usrgrpids = array_column($user['usrgrps'], 'usrgrpid');
				foreach ($usrgrpids as $usrgrpid) {
					API::UserGroup()->update(['usrgrpid' => $usrgrpid, 'gui_access' => GROUP_GUI_ACCESS_DISABLED]);
				}
				self::addQueueEntry((string) $user['userid'], $user['username'], $requested_by,
					$request_no, $comment, 'approved');
			}
			else { // flag_approval
				self::addQueueEntry((string) $user['userid'], $user['username'], $requested_by,
					$request_no, $comment, 'pending');
			}
			$processed[] = $user['username'];
		}

		$this->setResponse(new CControllerResponseData([
			'main_block' => json_encode(['success' => true, 'processed' => $processed])
		]));
	}
}
