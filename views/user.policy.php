<?php

/**
 * @var CView $this
 * @var array $data
 */

$policy = $data['policy'];
$counts = $data['counts'];
$filter = $data['filter'];

function umg_select(string $name, array $options, string $selected): string {
	$html = '<select name="' . htmlspecialchars($name) . '" class="umg-select">';
	foreach ($options as $value => $label) {
		$sel = ($value === $selected) ? ' selected' : '';
		$html .= '<option value="' . htmlspecialchars($value) . '"' . $sel . '>' . htmlspecialchars($label) . '</option>';
	}
	return $html . '</select>';
}

/*
 * ------------------------------------------------------------
 * Summary cards
 * ------------------------------------------------------------
 */
$cards_html = '<div class="umg-cards">'
	. '<div class="umg-card"><div class="umg-card-title">' . _('Total Users') . '</div>'
	. '<div class="umg-card-value">' . (int) $data['all_users_count'] . '</div>'
	. '<div class="umg-card-desc">' . _('Current Zabbix users') . '</div></div>'

	. '<div class="umg-card"><div class="umg-card-title">' . _('Never Logged In') . '</div>'
	. '<div class="umg-card-value">' . (int) $counts['never_logged_in'] . '</div>'
	. '<div class="umg-card-desc">' . _('No successful login on record') . '</div></div>'

	. '<div class="umg-card"><div class="umg-card-title">' . _s('Inactive > %1$d Days', $policy['inactivity_threshold_days']) . '</div>'
	. '<div class="umg-card-value">' . (int) $counts['inactive_45'] . '</div>'
	. '<div class="umg-card-desc">' . _('Logged in before, gone quiet since') . '</div></div>'

	. '<div class="umg-card umg-card-highlight"><div class="umg-card-title">' . _('Recommended Disable') . '</div>'
	. '<div class="umg-card-value">' . (int) $counts['recommend_disable'] . '</div>'
	. '<div class="umg-card-desc">' . _('Users meeting current policy') . '</div></div>'
	. '</div>';

/*
 * ------------------------------------------------------------
 * Filters
 * ------------------------------------------------------------
 */
$filters_html = '<div class="umg-filters"><h2>' . _('Filter Users') . '</h2>'
	. '<div class="umg-filter-row">'
	. '<div class="umg-filter"><label>' . _('User') . '</label>'
	. '<input type="text" name="filter_username" placeholder="' . _('Username') . '" value="' . htmlspecialchars($filter['username']) . '"></div>'

	. '<div class="umg-filter"><label>' . _('Activity') . '</label>' . umg_select('filter_activity', [
		'all' => _('All Users'), 'never' => _('Never Logged In'),
		'logged_in' => _('Logged In'), 'inactive' => _s('Inactive > %1$d Days', $policy['inactivity_threshold_days'])
	], $filter['activity']) . '</div>'

	. '<div class="umg-filter"><label>' . _('Account Age') . '</label>' . umg_select('filter_account_age', [
		'all' => _('All'), 'gt60' => _s('> %1$d Days', $policy['min_account_age_days']),
		'lt60' => _s('<= %1$d Days', $policy['min_account_age_days'])
	], $filter['account_age']) . '</div>'

	. '<div class="umg-filter"><label>' . _('Recommendation') . '</label>' . umg_select('filter_recommendation', [
		'all' => _('All'), 'disable' => _('Disable'), 'no_action' => _('No Action')
	], $filter['recommendation']) . '</div>'

	. '<div class="umg-filter"><label>' . _('Status') . '</label>' . umg_select('filter_status', [
		'enabled' => _('Enabled'), 'disabled' => _('Disabled'), 'all' => _('All')
	], $filter['status']) . '</div>'

	. '<button type="button" class="btn-primary" id="umg-filter-apply">' . _('Apply') . '</button>'
	. '<button type="button" id="umg-filter-reset">' . _('Reset') . '</button>'
	. '</div></div>';

/*
 * ------------------------------------------------------------
 * Results table
 * ------------------------------------------------------------
 */
$rows_html = '';
foreach ($data['users'] as $user) {
	$creation_display = $user['creation_clock'] !== null
		? zbx_date2str(DATE_TIME_FORMAT_SECONDS, $user['creation_clock']) : _('Not found');

	$login_display = $user['last_login_clock'] !== null
		? zbx_date2str(DATE_TIME_FORMAT_SECONDS, $user['last_login_clock'])
		: '<span class="umg-status umg-status-warning">' . _('Never') . '</span>';

	if ($user['last_login_clock'] === null) {
		$activity_badge = '<span class="umg-status umg-status-danger">' . _('Never Logged In') . '</span>';
	}
	elseif ($user['inactive_days'] > $policy['inactivity_threshold_days']) {
		$activity_badge = '<span class="umg-status umg-status-danger">' . _('Inactive') . '</span>';
	}
	else {
		$activity_badge = '<span class="umg-status umg-status-ok">' . _('Active') . '</span>';
	}
	if ($user['account_age_days'] !== null && $user['account_age_days'] <= $policy['min_account_age_days']
			&& $user['last_login_clock'] === null) {
		$activity_badge = '<span class="umg-status umg-status-info">' . _('New Account') . '</span>';
	}

	if ($user['is_disabled']) {
		$rec_badge = '<span class="umg-status umg-status-info">' . _('Already Disabled') . '</span>';
	}
	elseif ($user['pending_approval']) {
		$rec_badge = '<span class="umg-status umg-status-warning">' . _('Pending Approval') . '</span>';
	}
	elseif ($user['recommendation'] === 'disable') {
		$rec_badge = '<span class="umg-status umg-status-danger">' . _('Disable') . '</span>';
	}
	else {
		$rec_badge = '<span class="umg-status umg-status-ok">' . _('No Action') . '</span>';
	}

	$action_cell = '—';
	$checkbox_disabled = '';
	if (!$user['is_disabled'] && !$user['pending_approval']) {
		$action_cell = '<button type="button" class="btn-danger umg-row-disable" '
			. 'data-userid="' . (int) $user['userid'] . '" data-username="' . htmlspecialchars($user['username']) . '">'
			. _('Disable') . '</button>';
	}
	else {
		$checkbox_disabled = ' disabled';
	}

	$rows_html .= '<tr>'
		. '<td class="umg-col-check"><input type="checkbox" class="umg-row-check" data-userid="' . (int) $user['userid'] . '"' . $checkbox_disabled . '></td>'
		. '<td><span class="umg-username">' . htmlspecialchars($user['username']) . '</span>'
		. '<span class="umg-subtext">' . _s('User ID: %1$s', $user['userid']) . '</span></td>'
		. '<td>' . $creation_display . '</td>'
		. '<td>' . $login_display . '</td>'
		. '<td>' . ($user['account_age_days'] !== null ? _s('%1$d days', $user['account_age_days']) : '—') . '</td>'
		. '<td>' . ($user['inactive_days'] !== null ? _s('%1$d days', $user['inactive_days']) : '—') . '</td>'
		. '<td>' . $activity_badge . '</td>'
		. '<td>' . $rec_badge . '</td>'
		. '<td>' . $action_cell . '</td>'
		. '</tr>';
}

$results_html = '<div class="umg-results">'
	. '<div class="umg-results-header"><h2>' . _('Inactive User Review') . '</h2>'
	. '<span>' . _s('%1$d users match the current filters', count($data['users'])) . '</span></div>'
	. '<div class="umg-table-wrap"><table class="umg-table"><thead><tr>'
	. '<th class="umg-col-check"><input type="checkbox" id="umg-select-all"></th>'
	. '<th>' . _('User') . '</th><th>' . _('Account Created') . '</th><th>' . _('Last Login') . '</th>'
	. '<th>' . _('Account Age') . '</th><th>' . _('Inactive For') . '</th><th>' . _('Activity') . '</th>'
	. '<th>' . _('Recommendation') . '</th><th>' . _('Action') . '</th>'
	. '</tr></thead><tbody>' . $rows_html . '</tbody></table></div>'
	. '<div class="umg-footer"><div class="umg-footer-info">' . _s('Showing %1$d users', count($data['users'])) . '</div>'
	. '<div class="umg-bulk-actions">'
	. '<button type="button" id="umg-export-csv">' . _('Export CSV') . '</button>'
	. '<button type="button" id="umg-bulk-flag">' . _('Flag Selected for Approval') . '</button>'
	. '<button type="button" class="btn-danger" id="umg-bulk-disable">' . _('Disable Selected Users') . '</button>'
	. '</div></div></div>';

/*
 * ------------------------------------------------------------
 * Policy panel
 * ------------------------------------------------------------
 */
$policy_html = '<div class="umg-policy"><h2>' . _('Current Inactivity Policy') . '</h2>'
	. '<div class="umg-policy-grid">'
	. '<div class="umg-policy-item"><div class="umg-policy-label">' . _('Minimum Account Age') . '</div>'
	. '<div class="umg-policy-value">' . _s('%1$d Days', $policy['min_account_age_days']) . '</div></div>'
	. '<div class="umg-policy-item"><div class="umg-policy-label">' . _('Inactivity Threshold') . '</div>'
	. '<div class="umg-policy-value">' . _s('%1$d Days', $policy['inactivity_threshold_days']) . '</div></div>'
	. '<div class="umg-policy-item"><div class="umg-policy-label">' . _('Never Logged In') . '</div>'
	. '<div class="umg-policy-value">' . _s('Disable if account > %1$d days', $policy['min_account_age_days']) . '</div></div>'
	. '</div></div>';

/*
 * ------------------------------------------------------------
 * Modal
 * ------------------------------------------------------------
 */
$modal_html = '<div id="umg-modal-backdrop" class="umg-modal-backdrop"><div class="umg-modal">'
	. '<h3>' . _('Confirm Action') . '</h3>'
	. '<div class="umg-modal-desc" id="umg-modal-desc"></div>'
	. '<div class="umg-filter"><label>' . _('Request No.') . '</label>'
	. '<input type="text" id="umg_request_no" placeholder="e.g. CHG0012345"></div>'
	. '<div class="umg-filter"><label>' . _('Comment') . ' *</label>'
	. '<textarea id="umg_comment" placeholder="' . _('Reason for this action (required)') . '"></textarea></div>'
	. '<div class="umg-modal-actions">'
	. '<button type="button" id="umg-modal-cancel">' . _('Cancel') . '</button>'
	. '<button type="button" id="umg-modal-flag">' . _('Flag for Approval') . '</button>'
	. '<button type="button" class="btn-danger" id="umg-modal-confirm">' . _('Disable Now') . '</button>'
	. '</div></div></div>';

$css = <<<CSS
.umg-cards { display: grid; grid-template-columns: repeat(4, 1fr); gap: 16px; margin-bottom: 20px; }
.umg-card { background: #fff; border: 1px solid #d9dde3; border-radius: 4px; padding: 16px 18px; }
.umg-card-highlight { border-color: #d94b4b; }
.umg-card-title { color: #6b7280; font-size: 12px; margin-bottom: 8px; }
.umg-card-value { font-size: 26px; font-weight: 600; }
.umg-card-desc { font-size: 11px; color: #7b8490; margin-top: 4px; }
.umg-filters, .umg-results, .umg-policy { background: #fff; border: 1px solid #d9dde3; border-radius: 4px; margin-bottom: 20px; }
.umg-filters, .umg-policy { padding: 16px 18px; }
.umg-filter-row { display: flex; gap: 12px; align-items: end; flex-wrap: wrap; }
.umg-filter { display: flex; flex-direction: column; gap: 4px; min-width: 160px; }
.umg-filter label { font-size: 11px; color: #5f6b78; }
.umg-select, .umg-filter input, .umg-filter textarea { height: 34px; border: 1px solid #c7cdd4; border-radius: 3px; padding: 0 10px; }
.umg-filter textarea { height: 70px; padding: 8px 10px; font-family: inherit; }
.umg-filters button, .umg-bulk-actions button, .umg-modal-actions button { height: 34px; border: 1px solid #b8bec6; background: #fff; border-radius: 3px; padding: 0 15px; cursor: pointer; font-size: 13px; }
.btn-primary { background: #1f8dd6 !important; border-color: #1f8dd6 !important; color: #fff; }
.btn-danger { background: #d94b4b !important; border-color: #d94b4b !important; color: #fff; }
.umg-results-header { padding: 14px 18px; border-bottom: 1px solid #d9dde3; display: flex; justify-content: space-between; align-items: center; }
.umg-table-wrap { overflow-x: auto; width: 100%; }
table.umg-table { width: 100%; min-width: 900px; border-collapse: collapse; table-layout: auto; }
table.umg-table th, table.umg-table td { padding: 10px 12px; border-bottom: 1px solid #e7e9ec; text-align: left; vertical-align: middle; white-space: nowrap; }
table.umg-table th { background: #f6f7f9; font-size: 12px; color: #4b5563; font-weight: 600; }
table.umg-table td:nth-child(2) { white-space: normal; min-width: 160px; }
.umg-col-check { width: 34px; }
.umg-username { font-weight: 600; display: block; }
.umg-subtext { font-size: 11px; color: #7b8490; display: block; margin-top: 2px; }
.umg-status { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; white-space: nowrap; }
.umg-status-danger { background: #fde8e8; color: #b42323; }
.umg-status-warning { background: #fff4d6; color: #8a6200; }
.umg-status-ok { background: #e6f6ed; color: #176b3a; }
.umg-status-info { background: #e8f1fb; color: #175a9d; }
.umg-footer { padding: 14px 18px; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #d9dde3; flex-wrap: wrap; gap: 10px; }
.umg-bulk-actions { display: flex; gap: 8px; }
.umg-policy-grid { display: grid; grid-template-columns: repeat(3, 1fr); gap: 12px; }
.umg-policy-item { background: #f7f8fa; border: 1px solid #e0e3e7; padding: 10px 12px; border-radius: 3px; }
.umg-policy-label { font-size: 11px; color: #6b7280; margin-bottom: 4px; }
.umg-policy-value { font-weight: 600; }
.umg-modal-backdrop { display: none; position: fixed; inset: 0; background: rgba(0,0,0,.4); z-index: 1000; align-items: center; justify-content: center; }
.umg-modal-backdrop.umg-open { display: flex; }
.umg-modal { background: #fff; border-radius: 6px; padding: 22px; width: 420px; max-width: 90vw; display: flex; flex-direction: column; gap: 12px; }
.umg-modal-desc { font-size: 13px; color: #5f6b78; }
.umg-modal-actions { display: flex; justify-content: flex-end; gap: 8px; margin-top: 8px; }
CSS;

$js = <<<JS
(function() {
	function qs(sel, ctx) { return (ctx || document).querySelector(sel); }
	function qsa(sel, ctx) { return Array.prototype.slice.call((ctx || document).querySelectorAll(sel)); }

	var selected = {};

	qsa('.umg-row-check').forEach(function(cb) {
		cb.addEventListener('change', function() { selected[cb.dataset.userid] = cb.checked; });
	});

	var selectAll = qs('#umg-select-all');
	if (selectAll) {
		selectAll.addEventListener('change', function() {
			qsa('.umg-row-check:not(:disabled)').forEach(function(cb) {
				cb.checked = selectAll.checked;
				selected[cb.dataset.userid] = cb.checked;
			});
		});
	}

	function selectedIds() {
		return Object.keys(selected).filter(function(id) { return selected[id]; });
	}

	var modalBackdrop = qs('#umg-modal-backdrop');
	var pendingIds = [];

	function openModal(ids) {
		pendingIds = ids;
		qs('#umg_request_no').value = '';
		qs('#umg_comment').value = '';
		qs('#umg-modal-desc').textContent = ids.length > 1
			? ids.length + ' users selected. Choose an action below.'
			: 'This will affect 1 user. Choose an action below.';
		modalBackdrop.classList.add('umg-open');
	}

	qsa('.umg-row-disable').forEach(function(btn) {
		btn.addEventListener('click', function() { openModal([btn.dataset.userid]); });
	});

	var bulkDisableBtn = qs('#umg-bulk-disable');
	if (bulkDisableBtn) {
		bulkDisableBtn.addEventListener('click', function() {
			var ids = selectedIds();
			if (ids.length === 0) { alert('Select at least one user.'); return; }
			openModal(ids);
		});
	}

	var bulkFlagBtn = qs('#umg-bulk-flag');
	if (bulkFlagBtn) {
		bulkFlagBtn.addEventListener('click', function() {
			var ids = selectedIds();
			if (ids.length === 0) { alert('Select at least one user.'); return; }
			submitAction(ids, 'flag_approval', '', '');
		});
	}

	var cancelBtn = qs('#umg-modal-cancel');
	if (cancelBtn) cancelBtn.addEventListener('click', function() { modalBackdrop.classList.remove('umg-open'); });

	var flagBtn = qs('#umg-modal-flag');
	if (flagBtn) flagBtn.addEventListener('click', function() {
		submitAction(pendingIds, 'flag_approval', qs('#umg_request_no').value, qs('#umg_comment').value);
	});

	var confirmBtn = qs('#umg-modal-confirm');
	if (confirmBtn) confirmBtn.addEventListener('click', function() {
		var comment = qs('#umg_comment').value.trim();
		if (!comment) { alert('A comment is required to disable directly.'); return; }
		submitAction(pendingIds, 'disable_now', qs('#umg_request_no').value, comment);
	});

	function submitAction(ids, mode, requestNo, comment) {
		var params = new URLSearchParams();
		ids.forEach(function(id) { params.append('userids[]', id); });
		params.append('mode', mode);
		params.append('request_no', requestNo || '');
		params.append('comment', comment || '');

		fetch('zabbix.php?action=user.policy.execute', {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: params.toString()
		})
		.then(function(r) { return r.json(); })
		.then(function(data) {
			modalBackdrop.classList.remove('umg-open');
			if (data && data.success === false) {
				alert(data.error || 'Action failed.');
			}
			else {
				location.reload();
			}
		})
		.catch(function(err) {
			alert('Request failed: ' + err);
		});
	}

	var applyBtn = qs('#umg-filter-apply');
	if (applyBtn) {
		applyBtn.addEventListener('click', function() {
			var params = new URLSearchParams();
			params.append('filter_username', qs('[name=filter_username]').value);
			['filter_activity', 'filter_account_age', 'filter_recommendation', 'filter_status'].forEach(function(name) {
				var el = qs('[name=' + name + ']');
				if (el) params.append(name, el.value);
			});
			window.location.href = 'zabbix.php?action=user.policy&' + params.toString();
		});
	}

	var resetBtn = qs('#umg-filter-reset');
	if (resetBtn) {
		resetBtn.addEventListener('click', function() {
			window.location.href = 'zabbix.php?action=user.policy&filter_rst=1';
		});
	}

	var exportBtn = qs('#umg-export-csv');
	if (exportBtn) {
		exportBtn.addEventListener('click', function() {
			var rows = [['User ID', 'Username', 'Account Created', 'Last Login', 'Account Age (days)', 'Inactive (days)', 'Recommendation']];
			qsa('.umg-table tbody tr').forEach(function(tr) {
				var cells = tr.querySelectorAll('td');
				if (cells.length < 9) return;
				var uname = cells[1].querySelector('.umg-username');
				var uid = cells[1].querySelector('.umg-subtext');
				rows.push([
					uid ? uid.textContent.replace('User ID: ', '') : '',
					uname ? uname.textContent : '',
					cells[2].textContent.trim(),
					cells[3].textContent.trim(),
					cells[4].textContent.trim(),
					cells[5].textContent.trim(),
					cells[7].textContent.trim()
				]);
			});
			var csv = rows.map(function(r) {
				return r.map(function(v) { return '"' + String(v).replace(/"/g, '""') + '"'; }).join(',');
			}).join('\\n');
			var blob = new Blob([csv], { type: 'text/csv' });
			var a = document.createElement('a');
			a.href = URL.createObjectURL(blob);
			a.download = 'user_management_export.csv';
			a.click();
		});
	}
})();
JS;

(new CHtmlPage())
	->setTitle($data['title'])
	->addItem(new CTag('style', false, $css))
	->addItem(new CTag('div', false, $cards_html))
	->addItem(new CTag('div', false, $filters_html))
	->addItem(new CTag('div', false, $results_html))
	->addItem(new CTag('div', false, $policy_html))
	->addItem(new CTag('div', false, $modal_html))
	->addItem(new CTag('script', false, $js))
	->show();
