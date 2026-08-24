<?php
/**
 * @var CView $this
 * @var array $data
 */

$config = $data['config'];
$summary = $data['summary'];
$pending_queue = $data['pending_queue'];

function umg_esc($v) {
	return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}
?>
<h1><?= umg_esc($data['title']) ?></h1>

<style>
* { box-sizing: border-box; }
.umg-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin: 16px 0 22px 0; }
.umg-card { background: #fff; border: 1px solid #e1e5ea; border-left: 4px solid #97aab3; border-radius: 6px; padding: 16px 18px; box-shadow: 0 1px 2px rgba(16,24,40,0.04); transition: box-shadow .15s ease; }
.umg-card:hover { box-shadow: 0 2px 8px rgba(16,24,40,0.08); }
.umg-card.umg-accent-danger { border-left-color: #d94b4b; }
.umg-card.umg-accent-warning { border-left-color: #d29b1f; }
.umg-card.umg-accent-ok { border-left-color: #2f9e5c; }
.umg-card-title { color: #6b7280; font-size: 12px; margin-bottom: 8px; text-transform: uppercase; letter-spacing: .03em; }
.umg-card-value { font-size: 28px; font-weight: 700; color: #1f2937; }

.umg-panel { background: #fff; border: 1px solid #e1e5ea; border-radius: 6px; padding: 18px 20px; margin-bottom: 20px; box-shadow: 0 1px 2px rgba(16,24,40,0.04); }
.umg-panel h2 { font-size: 15px; font-weight: 700; margin: 0 0 14px 0; color: #1f2937; }
.umg-panel h2 .umg-count-pill { display: inline-block; background: #eef1f4; color: #4b5563; font-size: 11px; font-weight: 600; padding: 2px 8px; border-radius: 10px; margin-left: 8px; vertical-align: middle; }

.umg-filter-row { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; }
.umg-filter { display: flex; flex-direction: column; gap: 5px; }
.umg-filter label { font-size: 11px; color: #5f6b78; font-weight: 600; }
.umg-filter select, .umg-filter input { height: 32px; border: 1px solid #c7cdd4; border-radius: 4px; padding: 0 9px; min-width: 170px; font-size: 13px; }
.umg-filter select:focus, .umg-filter input:focus { outline: none; border-color: #2f7dd1; box-shadow: 0 0 0 3px rgba(47,125,209,0.12); }

.umg-table-wrap { overflow-x: auto; }
.umg-table { width: 100%; border-collapse: collapse; }
.umg-table th { position: sticky; top: 0; background: #f6f7f9; text-align: left; padding: 9px 10px; font-size: 11px; text-transform: uppercase; letter-spacing: .03em; color: #4b5563; border-bottom: 2px solid #e1e5ea; white-space: nowrap; }
.umg-table td { padding: 9px 10px; border-bottom: 1px solid #eef0f2; font-size: 13px; vertical-align: middle; }
.umg-table tbody tr { transition: background-color .1s ease; }
.umg-table tbody tr:hover { background: #f8fafc; }
.umg-table tbody tr.umg-row-checked { background: #eef6ff; }
.umg-username { font-weight: 600; color: #1f2937; }
.umg-subtext { display: block; font-size: 11px; color: #7b8490; margin-top: 1px; }
.umg-comment-text { font-size: 12px; color: #4b5563; max-width: 220px; white-space: normal; }

.umg-badge { display: inline-block; padding: 3px 9px; border-radius: 12px; font-size: 11px; font-weight: 700; }
.umg-badge-danger { background: #fde8e8; color: #b42323; }
.umg-badge-warning { background: #fff4d6; color: #8a6200; }
.umg-badge-ok { background: #e6f6ed; color: #176b3a; }
.umg-badge-info { background: #e8f1fb; color: #175a9d; }

.umg-results-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 12px; flex-wrap: wrap; gap: 8px; }
.umg-results-header span { color: #6b7280; font-size: 12px; }
.umg-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 14px; flex-wrap: wrap; gap: 10px; }
.umg-bulk-actions { display: flex; gap: 8px; flex-wrap: wrap; }

.umg-modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(15,23,42,0.45); z-index: 9999; align-items: center; justify-content: center; }
.umg-modal-backdrop.umg-open { display: flex; }
.umg-modal { background: #fff; border-radius: 8px; padding: 22px; width: 440px; max-width: 90vw; box-shadow: 0 10px 40px rgba(0,0,0,0.25); }
.umg-modal h3 { margin: 0 0 12px 0; font-size: 16px; }
.umg-modal label { font-size: 12px; font-weight: 600; color: #4b5563; }
.umg-modal textarea, .umg-modal input[type=text] { width: 100%; box-sizing: border-box; margin-top: 6px; margin-bottom: 14px; padding: 9px; border: 1px solid #c7cdd4; border-radius: 4px; font-size: 13px; }
.umg-modal-actions { display: flex; justify-content: flex-end; gap: 8px; flex-wrap: wrap; }
.umg-row-hidden { display: none !important; }

button.umg-btn { height: 32px; border: 1px solid #c7cdd4; background: #fff; border-radius: 4px; padding: 0 15px; cursor: pointer; font-size: 13px; font-weight: 600; color: #374151; transition: background-color .1s ease, border-color .1s ease; }
button.umg-btn:hover { background: #f0f2f5; border-color: #aab2bb; }
button.umg-btn:disabled { opacity: .5; cursor: not-allowed; }
button.umg-btn-danger { background: #d94b4b; border-color: #d94b4b; color: #fff; }
button.umg-btn-danger:hover { background: #c43e3e; border-color: #c43e3e; }
button.umg-btn-primary { background: #2f7dd1; border-color: #2f7dd1; color: #fff; }
button.umg-btn-primary:hover { background: #256bb8; border-color: #256bb8; }
button.umg-btn-ghost { background: #fff; }
button.umg-btn-sm { height: 26px; padding: 0 10px; font-size: 12px; }

.umg-empty-state { text-align: center; padding: 30px 10px; color: #8a94a3; font-size: 13px; }
.umg-approval-item { display: flex; justify-content: space-between; align-items: flex-start; gap: 16px; padding: 12px 0; border-bottom: 1px solid #eef0f2; flex-wrap: wrap; }
.umg-approval-item:last-child { border-bottom: none; }
.umg-approval-meta { flex: 1; min-width: 220px; }
.umg-approval-meta .umg-username { font-size: 14px; }
.umg-approval-comment { font-size: 12px; color: #4b5563; margin-top: 4px; background: #f6f7f9; border-radius: 4px; padding: 6px 9px; }
.umg-approval-flagged { font-size: 11px; color: #8a94a3; margin-top: 4px; }
.umg-approval-actions { display: flex; gap: 8px; align-items: center; }
</style>

<div class="umg-cards">
	<div class="umg-card">
		<div class="umg-card-title"><?= _('Total Users') ?></div>
		<div class="umg-card-value"><?= umg_esc($summary['total']) ?></div>
	</div>
	<div class="umg-card umg-accent-danger">
		<div class="umg-card-title"><?= _('Never Logged In') ?></div>
		<div class="umg-card-value"><?= umg_esc($summary['never_logged_in']) ?></div>
	</div>
	<div class="umg-card umg-accent-danger">
		<div class="umg-card-title"><?= _('Inactive Over Threshold') ?></div>
		<div class="umg-card-value"><?= umg_esc($summary['inactive_over_threshold']) ?></div>
	</div>
	<div class="umg-card umg-accent-warning">
		<div class="umg-card-title"><?= _('Recommended Disable') ?></div>
		<div class="umg-card-value"><?= umg_esc($summary['recommended_disable']) ?></div>
	</div>
</div>

<?php if ($pending_queue): ?>
<div class="umg-panel">
	<h2><?= _('Pending Approvals') ?> <span class="umg-count-pill"><?= count($pending_queue) ?></span></h2>
<?php foreach ($pending_queue as $entry): ?>
	<div class="umg-approval-item">
		<div class="umg-approval-meta">
			<div class="umg-username"><?= umg_esc($entry['username'] ?? ('User ID: ' . $entry['userid'])) ?></div>
			<?php if (!empty($entry['comment'])): ?>
			<div class="umg-approval-comment"><?= umg_esc($entry['comment']) ?></div>
			<?php endif; ?>
			<div class="umg-approval-flagged">
				<?= _('Flagged by') ?> <?= umg_esc($entry['flagged_by'] ?? '—') ?>
				<?php if (!empty($entry['flagged_at'])): ?>
					&middot; <?= umg_esc(zbx_date2str(DATE_TIME_FORMAT_SECONDS, $entry['flagged_at'])) ?>
				<?php endif; ?>
			</div>
		</div>
		<div class="umg-approval-actions">
			<button type="button" class="umg-btn umg-btn-sm umg-btn-ghost umg-reject-btn" data-index="<?= umg_esc($entry['queue_index']) ?>"><?= _('Reject') ?></button>
			<button type="button" class="umg-btn umg-btn-sm umg-btn-primary umg-approve-btn" data-index="<?= umg_esc($entry['queue_index']) ?>"><?= _('Approve & Disable') ?></button>
		</div>
	</div>
<?php endforeach; ?>
</div>
<?php endif; ?>

<div class="umg-panel">
	<h2><?= _('Filter Users') ?></h2>
	<div class="umg-filter-row">
		<div class="umg-filter">
			<label><?= _('User') ?></label>
			<input type="text" id="umg-filter-username" placeholder="<?= _('Username') ?>">
		</div>
		<div class="umg-filter">
			<label><?= _('Activity') ?></label>
			<select id="umg-filter-activity">
				<option value=""><?= _('All Users') ?></option>
				<option value="never_logged_in"><?= _('Never Logged In') ?></option>
				<option value="inactive"><?= _('Inactive') ?></option>
				<option value="active"><?= _('Active') ?></option>
				<option value="new_account"><?= _('New Account') ?></option>
				<option value="already_disabled"><?= _('Already Disabled') ?></option>
			</select>
		</div>
		<div class="umg-filter">
			<label><?= _('Recommendation') ?></label>
			<select id="umg-filter-recommendation">
				<option value=""><?= _('All') ?></option>
				<option value="disable"><?= _('Disable') ?></option>
				<option value="no_action"><?= _('No Action') ?></option>
			</select>
		</div>
		<button type="button" class="umg-btn" id="umg-filter-reset"><?= _('Reset') ?></button>
	</div>
</div>

<div class="umg-panel">
	<div class="umg-results-header">
		<h2 style="margin:0;"><?= _('Inactive User Review') ?></h2>
		<span id="umg-match-count"></span>
	</div>

	<div class="umg-table-wrap">
	<table class="umg-table" id="umg-table">
		<thead>
			<tr>
				<th><input type="checkbox" id="umg-select-all"></th>
				<th><?= _('User') ?></th>
				<th><?= _('Account Created') ?></th>
				<th><?= _('Last Login') ?></th>
				<th><?= _('Account Age') ?></th>
				<th><?= _('Inactive For') ?></th>
				<th><?= _('Activity') ?></th>
				<th><?= _('Recommendation') ?></th>
				<th><?= _('Comment') ?></th>
				<th><?= _('Action') ?></th>
			</tr>
		</thead>
		<tbody>
<?php foreach ($data['users'] as $user): ?>
<?php
	$creation_str = $user['creation_clock'] !== null
		? zbx_date2str(DATE_TIME_FORMAT_SECONDS, $user['creation_clock'])
		: _('Not found');

	$login_str = $user['last_login_clock'] !== null
		? zbx_date2str(DATE_TIME_FORMAT_SECONDS, $user['last_login_clock'])
		: _('Never');

	$account_age = $user['creation_age_days'] !== null ? $user['creation_age_days'] . ' ' . _('days') : '—';
	$inactive_for = $user['last_login_age_days'] !== null ? $user['last_login_age_days'] . ' ' . _('days') : '—';

	$activity_class = 'umg-badge-ok';
	$activity_label = _('Active');
	if ($user['reason'] === 'already_disabled') { $activity_class = 'umg-badge-info'; $activity_label = _('Already Disabled'); }
	elseif ($user['reason'] === 'never_logged_in') { $activity_class = 'umg-badge-danger'; $activity_label = _('Never Logged In'); }
	elseif ($user['reason'] === 'inactive') { $activity_class = 'umg-badge-danger'; $activity_label = _('Inactive'); }
	elseif ($user['reason'] === 'new_account') { $activity_class = 'umg-badge-info'; $activity_label = _('New Account'); }

	if ($user['pending_approval']) { $rec_class = 'umg-badge-warning'; $rec_label = _('Pending Approval'); }
	elseif ($user['reason'] === 'already_disabled') { $rec_class = 'umg-badge-info'; $rec_label = _('Already Disabled'); }
	elseif ($user['recommendation'] === 'disable') { $rec_class = 'umg-badge-danger'; $rec_label = _('Disable'); }
	else { $rec_class = 'umg-badge-ok'; $rec_label = _('No Action'); }

	$can_act = ($user['recommendation'] === 'disable' && !$user['pending_approval']);

	$comment_text = $user['disable_comment'] ?? $user['pending_comment'] ?? '';
	$comment_csv = $comment_text;
	if ($comment_text !== '' && !empty($user['disabled_by'])) {
		$comment_csv .= ' (' . $user['disabled_by'];
		if (!empty($user['disabled_at'])) {
			$comment_csv .= ', ' . zbx_date2str(DATE_TIME_FORMAT_SECONDS, $user['disabled_at']);
		}
		$comment_csv .= ')';
	}
?>
			<tr data-userid="<?= umg_esc($user['userid']) ?>" data-username="<?= umg_esc(mb_strtolower($user['username'])) ?>" data-activity="<?= umg_esc($user['reason']) ?>" data-recommendation="<?= umg_esc($user['recommendation']) ?>">
				<td><?php if ($can_act): ?><input type="checkbox" class="umg-row-checkbox"><?php endif; ?></td>
				<td>
					<div class="umg-username"><?= umg_esc($user['username']) ?></div>
					<div class="umg-subtext"><?= _('User ID:') ?> <?= umg_esc($user['userid']) ?></div>
				</td>
				<td><?= umg_esc($creation_str) ?></td>
				<td><?= umg_esc($login_str) ?></td>
				<td><?= umg_esc($account_age) ?></td>
				<td><?= umg_esc($inactive_for) ?></td>
				<td><span class="umg-badge <?= $activity_class ?>"><?= umg_esc($activity_label) ?></span></td>
				<td><span class="umg-badge <?= $rec_class ?>"><?= umg_esc($rec_label) ?></span></td>
				<td class="umg-comment-text" data-csv-comment="<?= umg_esc($comment_csv) ?>"><?= $comment_text !== '' ? umg_esc($comment_text) : '—' ?></td>
				<td>
<?php if ($can_act): ?>
					<button type="button" class="umg-btn umg-btn-danger umg-btn-sm umg-row-disable-btn" data-userid="<?= umg_esc($user['userid']) ?>"><?= _('Disable') ?></button>
<?php else: ?>
					—
<?php endif; ?>
				</td>
			</tr>
<?php endforeach; ?>
		</tbody>
	</table>
	</div>
	<?php if (!$data['users']): ?>
	<div class="umg-empty-state"><?= _('No users found.') ?></div>
	<?php endif; ?>

	<div class="umg-footer">
		<div class="umg-card-title" id="umg-footer-info" style="text-transform:none;"></div>
		<div class="umg-bulk-actions">
			<button type="button" class="umg-btn" id="umg-export-csv"><?= _('Export CSV') ?></button>
			<button type="button" class="umg-btn" id="umg-flag-selected"><?= _('Flag Selected for Approval') ?></button>
			<button type="button" class="umg-btn umg-btn-danger" id="umg-disable-selected"><?= _('Disable Selected Users') ?></button>
		</div>
	</div>
</div>

<div class="umg-panel">
	<h2><?= _('Inactivity Policy (configurable)') ?></h2>
	<div class="umg-filter-row">
		<div class="umg-filter">
			<label><?= _('Minimum Account Age (days)') ?></label>
			<input type="number" min="0" id="umg-cfg-min-age" value="<?= umg_esc($config['min_account_age_days']) ?>">
		</div>
		<div class="umg-filter">
			<label><?= _('Inactivity Threshold (days)') ?></label>
			<input type="number" min="0" id="umg-cfg-threshold" value="<?= umg_esc($config['inactivity_threshold_days']) ?>">
		</div>
		<button type="button" class="umg-btn umg-btn-primary" id="umg-cfg-save"><?= _('Save Policy') ?></button>
	</div>
</div>

<div class="umg-modal-backdrop" id="umg-modal-backdrop">
	<div class="umg-modal">
		<h3><?= _('Disable Users') ?></h3>
		<div class="umg-card-title" id="umg-modal-userlist" style="text-transform:none;margin-bottom:12px;"></div>
		<label><?= _('Request No. / Comment (required to disable immediately)') ?></label>
		<textarea rows="3" id="umg-modal-comment"></textarea>
		<div class="umg-modal-actions">
			<button type="button" class="umg-btn" id="umg-modal-cancel"><?= _('Cancel') ?></button>
			<button type="button" class="umg-btn" id="umg-modal-flag"><?= _('Flag for Approval') ?></button>
			<button type="button" class="umg-btn umg-btn-danger" id="umg-modal-confirm"><?= _('Disable Now') ?></button>
		</div>
	</div>
</div>

<div class="umg-modal-backdrop" id="umg-reject-modal-backdrop">
	<div class="umg-modal">
		<h3><?= _('Reject Request') ?></h3>
		<label><?= _('Reason (optional)') ?></label>
		<textarea rows="3" id="umg-reject-comment"></textarea>
		<div class="umg-modal-actions">
			<button type="button" class="umg-btn" id="umg-reject-cancel"><?= _('Cancel') ?></button>
			<button type="button" class="umg-btn umg-btn-danger" id="umg-reject-confirm"><?= _('Reject') ?></button>
		</div>
	</div>
</div>

<script>
(function () {
	var table = document.getElementById('umg-table');
	var rows = Array.prototype.slice.call(table.querySelectorAll('tbody tr'));

	function applyFilters() {
		var username = document.getElementById('umg-filter-username').value.trim().toLowerCase();
		var activity = document.getElementById('umg-filter-activity').value;
		var recommendation = document.getElementById('umg-filter-recommendation').value;
		var visible = 0;

		rows.forEach(function (row) {
			var matches = true;
			if (username && row.getAttribute('data-username').indexOf(username) === -1) matches = false;
			if (activity && row.getAttribute('data-activity') !== activity) matches = false;
			if (recommendation && row.getAttribute('data-recommendation') !== recommendation) matches = false;
			row.classList.toggle('umg-row-hidden', !matches);
			if (matches) visible++;
		});

		document.getElementById('umg-match-count').textContent = visible + ' matching users';
		document.getElementById('umg-footer-info').textContent = 'Showing ' + visible + ' of ' + rows.length + ' users';
	}

	document.getElementById('umg-filter-username').addEventListener('input', applyFilters);
	document.getElementById('umg-filter-activity').addEventListener('change', applyFilters);
	document.getElementById('umg-filter-recommendation').addEventListener('change', applyFilters);
	document.getElementById('umg-filter-reset').addEventListener('click', function () {
		document.getElementById('umg-filter-username').value = '';
		document.getElementById('umg-filter-activity').value = '';
		document.getElementById('umg-filter-recommendation').value = '';
		applyFilters();
	});

	var selectAll = document.getElementById('umg-select-all');
	selectAll.addEventListener('change', function () {
		table.querySelectorAll('.umg-row-checkbox').forEach(function (cb) {
			var row = cb.closest('tr');
			if (!row.classList.contains('umg-row-hidden')) {
				cb.checked = selectAll.checked;
				row.classList.toggle('umg-row-checked', cb.checked);
			}
		});
	});

	table.addEventListener('change', function (e) {
		if (e.target.classList.contains('umg-row-checkbox')) {
			e.target.closest('tr').classList.toggle('umg-row-checked', e.target.checked);
		}
	});

	function getSelectedUserIds() {
		return Array.prototype.slice.call(table.querySelectorAll('.umg-row-checkbox:checked'))
			.map(function (cb) { return cb.closest('tr').getAttribute('data-userid'); });
	}

	var modalBackdrop = document.getElementById('umg-modal-backdrop');
	var modalUserlist = document.getElementById('umg-modal-userlist');
	var modalComment = document.getElementById('umg-modal-comment');
	var pendingUserIds = [];

	function openModal(userIds) {
		pendingUserIds = userIds;
		modalUserlist.textContent = userIds.length + ' user(s) selected';
		modalComment.value = '';
		modalBackdrop.classList.add('umg-open');
	}
	function closeModal() {
		modalBackdrop.classList.remove('umg-open');
		pendingUserIds = [];
	}

	document.getElementById('umg-modal-cancel').addEventListener('click', closeModal);

	document.getElementById('umg-disable-selected').addEventListener('click', function () {
		var ids = getSelectedUserIds();
		if (!ids.length) { alert('Select at least one user.'); return; }
		openModal(ids);
	});

	document.getElementById('umg-flag-selected').addEventListener('click', function () {
		var ids = getSelectedUserIds();
		if (!ids.length) { alert('Select at least one user.'); return; }
		submitAction({ userids: ids, mode: 'flag', comment: '' });
	});

	table.addEventListener('click', function (e) {
		if (e.target.classList.contains('umg-row-disable-btn')) {
			openModal([e.target.getAttribute('data-userid')]);
		}
	});

	function submitAction(params) {
		var body = new URLSearchParams();
		(params.userids || []).forEach(function (id) { body.append('userids[]', id); });
		if (params.queue_index !== undefined) body.append('queue_index', params.queue_index);
		body.append('mode', params.mode);
		body.append('comment', params.comment || '');

		return fetch('zabbix.php?action=user.policy.execute', {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		})
			.then(function (r) { return r.json(); })
			.then(function (data) {
				alert(data.message || (data.success ? 'Done.' : 'Failed.'));
				if (data.success) window.location.reload();
			})
			.catch(function () { alert('Request failed.'); });
	}

	document.getElementById('umg-modal-confirm').addEventListener('click', function () {
		submitAction({ userids: pendingUserIds, mode: 'immediate', comment: modalComment.value.trim() });
		closeModal();
	});
	document.getElementById('umg-modal-flag').addEventListener('click', function () {
		submitAction({ userids: pendingUserIds, mode: 'flag', comment: modalComment.value.trim() });
		closeModal();
	});

	// Approve / reject pending requests
	document.querySelectorAll('.umg-approve-btn').forEach(function (btn) {
		btn.addEventListener('click', function () {
			if (!confirm('Disable this user now?')) return;
			submitAction({ mode: 'approve', queue_index: btn.getAttribute('data-index') });
		});
	});

	var rejectBackdrop = document.getElementById('umg-reject-modal-backdrop');
	var rejectComment = document.getElementById('umg-reject-comment');
	var rejectIndex = null;

	document.querySelectorAll('.umg-reject-btn').forEach(function (btn) {
		btn.addEventListener('click', function () {
			rejectIndex = btn.getAttribute('data-index');
			rejectComment.value = '';
			rejectBackdrop.classList.add('umg-open');
		});
	});
	document.getElementById('umg-reject-cancel').addEventListener('click', function () {
		rejectBackdrop.classList.remove('umg-open');
	});
	document.getElementById('umg-reject-confirm').addEventListener('click', function () {
		submitAction({ mode: 'reject', queue_index: rejectIndex, comment: rejectComment.value.trim() });
		rejectBackdrop.classList.remove('umg-open');
	});

	document.getElementById('umg-cfg-save').addEventListener('click', function () {
		var body = new URLSearchParams();
		body.append('min_account_age_days', document.getElementById('umg-cfg-min-age').value);
		body.append('inactivity_threshold_days', document.getElementById('umg-cfg-threshold').value);

		fetch('zabbix.php?action=user.policy.config', {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: body.toString()
		})
			.then(function (r) { return r.json(); })
			.then(function (data) {
				alert(data.message || (data.success ? 'Saved.' : 'Failed.'));
				if (data.success) window.location.reload();
			})
			.catch(function () { alert('Request failed.'); });
	});

	// CSV export of currently-visible rows, including disablement comment
	document.getElementById('umg-export-csv').addEventListener('click', function () {
		var header = ['Username', 'User ID', 'Account Created', 'Last Login', 'Account Age', 'Inactive For', 'Activity', 'Recommendation', 'Comment'];
		var lines = [header.join(',')];

		function csvField(text) {
			text = String(text == null ? '' : text);
			if (/[",\n]/.test(text)) {
				text = '"' + text.replace(/"/g, '""') + '"';
			}
			return text;
		}

		rows.forEach(function (row) {
			if (row.classList.contains('umg-row-hidden')) return;
			var cells = row.querySelectorAll('td');
			var username = cells[1].querySelector('.umg-username').textContent.trim();
			var userid = row.getAttribute('data-userid');
			var commentCell = row.querySelector('.umg-comment-text');
			var comment = commentCell ? commentCell.getAttribute('data-csv-comment') : '';

			var fields = [
				username,
				userid,
				cells[2].textContent.trim(),
				cells[3].textContent.trim(),
				cells[4].textContent.trim(),
				cells[5].textContent.trim(),
				cells[6].textContent.trim(),
				cells[7].textContent.trim(),
				comment
			];
			lines.push(fields.map(csvField).join(','));
		});

		var blob = new Blob([lines.join('\r\n')], { type: 'text/csv;charset=utf-8;' });
		var url = URL.createObjectURL(blob);
		var a = document.createElement('a');
		a.href = url;
		a.download = 'user_management_export.csv';
		document.body.appendChild(a);
		a.click();
		document.body.removeChild(a);
		URL.revokeObjectURL(url);
	});

	applyFilters();
})();
</script>
