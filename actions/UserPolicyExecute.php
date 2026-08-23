<?php

namespace Modules\UserMgmt\Actions;

use API;
use CController;
use CControllerResponseData;

require_once __DIR__ . '/../lib/CApprovalQueue.php';

use Modules\UserMgmt\Lib\CApprovalQueue;

class UserPolicyExecute extends CController {

	public function init(): void {
		$this->disableCsrfValidation();
	}

	protected function checkInput(): bool {
		$fields = [
			'userids'    => 'required|array_db users.userid',
			'mode'       => 'required|in disable_now,flag_approval,resolve_approval',
			'request_no' => 'string',
			'comment'    => 'string',
			'entry_id'   => 'string',
			'resolution' => 'in approved,rejected'
		];

		$ret = $this->validateInput($fields);

		if ($ret && $this->getInput('mode') === 'disable_now'
				&& trim($this->getInput('comment', '')) === '') {
			// Every direct disable must carry a reason — this is the audit trail an approval
			// workflow would otherwise provide.
			error(_('A comment is required when disabling accounts directly.'));
			$ret = false;
		}

		if (($errors = getMessages()) !== null) {
			$this->setResponse(
				(new CControllerResponseData(['main_block' => json_encode([
					'error' => ['messages' => array_column($errors, 'message')]
				])]))
			);
			$ret = false;
		}

		return $ret;
	}

	protected function checkPermissions(): bool {
		return $this->getUserType() >= USER_TYPE_SUPER_ADMIN;
	}

	protected function doAction(): void {
		$mode = $this->getInput('mode');
		$requested_by = \CWebUser::$data['username'] ?? (string) \CWebUser::$data['userid'];
		$result = ['success' => true, 'processed' => []];

		switch ($mode) {
			case 'disable_now':
				$userids = $this->getInput('userids');
				$request_no = $this->getInput('request_no', '');
				$comment = $this->getInput('comment', '');

				// Resolve each user's current groups, then set gui_access = DISABLED on all of them.
				// (Zabbix disables login at the user-group level, not per-user.)
				$users = API::User()->get([
					'output' => ['userid', 'username'],
					'selectUsrgrps' => ['usrgrpid'],
					'userids' => $userids
				]);

				foreach ($users as $user) {
					$usrgrpids = array_column($user['usrgrps'], 'usrgrpid');
					if (!$usrgrpids) {
						continue; // no groups to disable via — leave alone rather than guess
					}

					API::UserGroup()->update(array_map(function($id) {
						return ['usrgrpid' => $id, 'gui_access' => GROUP_GUI_ACCESS_DISABLED];
					}, $usrgrpids));

					// Audit trail note: Zabbix's own audit log already records the group update;
					// we additionally log request_no/comment into the approval queue file as a
					// resolved record so the "why" isn't lost, even outside the approval flow.
					CApprovalQueue::add(
						(string) $user['userid'], $user['username'], $requested_by,
						$request_no, $comment
					);
					$queue = CApprovalQueue::getAll();
					$last = end($queue);
					CApprovalQueue::resolve($last['id'], 'approved', $requested_by);

					$result['processed'][] = $user['username'];
				}
				break;

			case 'flag_approval':
				$userids = $this->getInput('userids');
				$request_no = $this->getInput('request_no', '');
				$comment = $this->getInput('comment', '');

				$users = API::User()->get([
					'output' => ['userid', 'username'],
					'userids' => $userids
				]);

				foreach ($users as $user) {
					if (CApprovalQueue::isPending((string) $user['userid'])) {
						continue;
					}
					CApprovalQueue::add(
						(string) $user['userid'], $user['username'], $requested_by,
						$request_no, $comment
					);
					$result['processed'][] = $user['username'];
				}
				break;

			case 'resolve_approval':
				// Approver reviews a pending flag and either confirms the disable or rejects it.
				// NOTE: this only records the decision and, on approval, disables the account —
				// it does not yet enforce that the resolver differs from the requester
				// (a maker-checker separation), which is worth adding once the approval
				// workflow's ownership is decided.
				$entry_id = $this->getInput('entry_id');
				$resolution = $this->getInput('resolution');

				$entry = null;
				foreach (CApprovalQueue::getAll() as $e) {
					if ($e['id'] === $entry_id) {
						$entry = $e;
						break;
					}
				}

				if ($entry === null) {
					$result['success'] = false;
					$result['error'] = _('Approval request not found.');
					break;
				}

				CApprovalQueue::resolve($entry_id, $resolution, $requested_by);

				if ($resolution === 'approved') {
					$user = API::User()->get([
						'output' => ['userid'],
						'selectUsrgrps' => ['usrgrpid'],
						'userids' => [$entry['userid']]
					]);
					if ($user) {
						$usrgrpids = array_column($user[0]['usrgrps'], 'usrgrpid');
						if ($usrgrpids) {
							API::UserGroup()->update(array_map(function($id) {
								return ['usrgrpid' => $id, 'gui_access' => GROUP_GUI_ACCESS_DISABLED];
							}, $usrgrpids));
						}
					}
				}

				$result['processed'][] = $entry['username'];
				break;
		}

		$this->setResponse(new CControllerResponseData([
			'main_block' => json_encode($result)
		]));
	}
}
