<?php
/**
 * @var CView $this
 * @var array $data
 */

$config = $data['config'];
$summary = $data['summary'];

function umg_esc($v) {
	return htmlspecialchars((string) $v, ENT_QUOTES, 'UTF-8');
}
?>
<h1><?= umg_esc($data['title']) ?></h1>

<style>
.umg-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 20px; }
.umg-card { background: #fff; border: 1px solid #d9dde3; border-radius: 4px; padding: 16px; }
.umg-card-title { color: #6b7280; font-size: 12px; margin-bottom: 8px; }
.umg-card-value { font-size: 26px; font-weight: 600; }
.umg-panel { background: #fff; border: 1px solid #d9dde3; border-radius: 4px; padding: 16px; margin-bottom: 20px; }
.umg-panel h2 { font-size: 15px; font-weight: 600; margin: 0 0 14px 0; }
.umg-filter-row { display: flex; gap: 12px; align-items: flex-end; flex-wrap: wrap; }
.umg-filter { display: flex; flex-direction: column; gap: 5px; }
.umg-filter label { font-size: 11px; color: #5f6b78; }
.umg-filter select, .umg-filter input { height: 30px; border: 1px solid #c7cdd4; border-radius: 3px; padding: 0 8px; min-width: 160px; }
.umg-table { width: 100%; border-collapse: collapse; }
.umg-table th { background: #f6f7f9; text-align: left; padding: 8px 10px; font-size: 12px; color: #4b5563; border-bottom: 1px solid #d9dde3; white-space: nowrap; }
.umg-table td { padding: 8px 10px; border-bottom: 1px solid #e7e9ec; font-size: 13px; vertical-align: middle; }
.umg-table tr:hover { background: #fafbfc; }
.umg-username { font-weight: 600; }
.umg-subtext { display: block; font-size: 11px; color: #7b8490; }
.umg-badge { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; }
.umg-badge-danger { background: #fde8e8; color: #b42323; }
.umg-badge-warning { background: #fff4d6; color: #8a6200; }
.umg-badge-ok { background: #e6f6ed; color: #176b3a; }
.umg-badge-info { background: #e8f1fb; color: #175a9d; }
.umg-results-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 10px; }
.umg-results-header span { color: #6b7280; font-size: 12px; }
.umg-footer { display: flex; justify-content: space-between; align-items: center; margin-top: 12px; }
.umg-bulk-actions { display: flex; gap: 8px; }
.umg-modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(0,0,0,0.4); z-index: 9999; align-items: center; justify-content: center; }
.umg-modal-backdrop.umg-open { display: flex; }
.umg-modal { background: #fff; border-radius: 5px; padding: 20px; width: 420px; max-width: 90vw; }
.umg-modal h3 { margin: 0 0 10px 0; }
.umg-modal textarea, .umg-modal input[type=text] { width: 100%; box-sizing: border-box; margin-top: 6px; margin-bottom: 14px; padding: 8px; border: 1px solid #c7cdd4; border-radius: 3px; }
.umg-modal-actions { display: flex; justify-content: flex-end; gap: 8px; }
.umg-row-hidden { display: none !important; }
button.umg-btn { height: 30px; border: 1px solid #b8bec6; background: #fff; border-radius: 3px; padding: 0 14px; cursor: pointer; font-size: 13px; }
button.umg-btn:hover { background: #f0f2f5; }
button.umg-btn-danger { background: #d94b4b; border-color: #d94b4b; color: #fff; }
button.umg-btn-danger:hover { background: #c43e3e; }
</style>

<div class="umg-cards">
	<div class="umg-card">
		<div class="umg-card-title"><?= _('Total Users') ?></div>
		<div class="umg-card-value"><?= umg_esc($summary['total']) ?></div>
	</div>
	<div class="umg-card">
		<div class="umg-card-title"><?= _('Never Logged In') ?></div>
		<div class="umg-card-value"><?= umg_esc($summary['never_logged_in']) ?></div>
	</div>
	<div class="umg-card">
		<div class="umg-card-title"><?= _('Inactive Over Threshold') ?></div>
		<div class="umg-card-value"><?= umg_esc($summary['inactive_over_threshold']) ?></div>
	</div>
	<div class="umg-card">
		<div class="umg-card-title"><?= _('Recommended Disable') ?></div>
		<div class="umg-card-value"><?= umg_esc($summary['recommended_disable']) ?></div>
	</div>
</div>

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
		<h2><?= _('Inactive User Review') ?></h2>
		<span id="umg-match-count"></span>
	</div>

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
	if ($user['reason'] === 'never_logged_in') { $activity_class = 'umg-badge-danger'; $activity_label = _('Never Logged In'); }
	elseif ($user['reason'] === 'inactive') { $activity_class = 'umg-badge-danger'; $activity_label = _('Inactive'); }
	elseif ($user['reason'] === 'new_account') { $activity_class = 'umg-badge-info'; $activity_label = _('New Account'); }

	if ($user['pending_approval']) { $rec_class = 'umg-badge-warning'; $rec_label = _('Pending Approval'); }
	elseif ($user['recommendation'] === 'disable') { $rec_class = 'umg-badge-danger'; $rec_label = _('Disable'); }
	else { $rec_class = 'umg-badge-ok'; $rec_label = _('No Action'); }

	$can_act = ($user['recommendation'] === 'disable' && !$user['pending_approval']);
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
				<td>
<?php if ($can_act): ?>
					<button type="button" class="umg-btn umg-btn-danger umg-row-disable-btn" data-userid="<?= umg_esc($user['userid']) ?>"><?= _('Disable') ?></button>
<?php else: ?>
					—
<?php endif; ?>
				</td>
			</tr>
<?php endforeach; ?>
		</tbody>
	</table>

	<div class="umg-footer">
		<div class="umg-card-title" id="umg-footer-info"></div>
		<div class="umg-bulk-actions">
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
		<button type="button" class="umg-btn" id="umg-cfg-save"><?= _('Save Policy') ?></button>
	</div>
</div>

<div class="umg-modal-backdrop" id="umg-modal-backdrop">
	<div class="umg-modal">
		<h3><?= _('Disable Users') ?></h3>
		<div class="umg-card-title" id="umg-modal-userlist"></div>
		<label><?= _('Request No. / Comment (required to disable immediately)') ?></label>
		<textarea rows="3" id="umg-modal-comment"></textarea>
		<div class="umg-modal-actions">
			<button type="button" class="umg-btn" id="umg-modal-cancel"><?= _('Cancel') ?></button>
			<button type="button" class="umg-btn" id="umg-modal-flag"><?= _('Flag for Approval') ?></button>
			<button type="button" class="umg-btn umg-btn-danger" id="umg-modal-confirm"><?= _('Disable Now') ?></button>
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
			if (!row.classList.contains('umg-row-hidden')) cb.checked = selectAll.checked;
		});
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
		submitAction(ids, 'flag', '');
	});

	table.addEventListener('click', function (e) {
		if (e.target.classList.contains('umg-row-disable-btn')) {
			openModal([e.target.getAttribute('data-userid')]);
		}
	});

	function submitAction(userIds, mode, comment) {
		var body = new URLSearchParams();
		userIds.forEach(function (id) { body.append('userids[]', id); });
		body.append('mode', mode);
		body.append('comment', comment);

		fetch('zabbix.php?action=user.policy.execute', {
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
		submitAction(pendingUserIds, 'immediate', modalComment.value.trim());
		closeModal();
	});
	document.getElementById('umg-modal-flag').addEventListener('click', function () {
		submitAction(pendingUserIds, 'flag', modalComment.value.trim());
		closeModal();
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

	applyFilters();
})();
</script>
