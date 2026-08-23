<?php
/**
 * @var CView $this
 * @var array $data
 */

$config = $data['config'];
$summary = $data['summary'];

/*
 * ------------------------------------------------------------
 * Styles (encode=false — CTag('style', true, ...) HTML-escapes
 * the CSS and silently breaks all layout; see module notes)
 * ------------------------------------------------------------
 */
$css = <<<CSS
.umg-header p { margin: 4px 0 0 0; color: #6b7280; }
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
.umg-table { width: 100%; border-collapse: collapse; table-layout: auto; }
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
.umg-pending { opacity: 0.65; }
CSS;

$style_tag = new CTag('style', false, $css);

/*
 * ------------------------------------------------------------
 * Summary cards
 * ------------------------------------------------------------
 */
$cards = (new CDiv())
	->addClass('umg-cards')
	->addItem((new CDiv())->addClass('umg-card')->addItem([
		(new CDiv(_('Total Users')))->addClass('umg-card-title'),
		(new CDiv((string) $summary['total']))->addClass('umg-card-value')
	]))
	->addItem((new CDiv())->addClass('umg-card')->addItem([
		(new CDiv(_('Never Logged In')))->addClass('umg-card-title'),
		(new CDiv((string) $summary['never_logged_in']))->addClass('umg-card-value')
	]))
	->addItem((new CDiv())->addClass('umg-card')->addItem([
		(new CDiv(_('Inactive Over Threshold')))->addClass('umg-card-title'),
		(new CDiv((string) $summary['inactive_over_threshold']))->addClass('umg-card-value')
	]))
	->addItem((new CDiv())->addClass('umg-card')->addItem([
		(new CDiv(_('Recommended Disable')))->addClass('umg-card-title'),
		(new CDiv((string) $summary['recommended_disable']))->addClass('umg-card-value')
	]));

/*
 * ------------------------------------------------------------
 * Filters
 * ------------------------------------------------------------
 */
$select_activity = new CTag('select', false, [
	new CTag('option', false, _('All Users')),
	(new CTag('option', false, _('Never Logged In')))->setAttribute('value', 'never_logged_in'),
	(new CTag('option', false, _('Inactive')))->setAttribute('value', 'inactive'),
	(new CTag('option', false, _('Active')))->setAttribute('value', 'active'),
	(new CTag('option', false, _('New Account')))->setAttribute('value', 'new_account')
]);
$select_activity->setId('umg-filter-activity');

$select_recommendation = new CTag('select', false, [
	new CTag('option', false, _('All')),
	(new CTag('option', false, _('Disable')))->setAttribute('value', 'disable'),
	(new CTag('option', false, _('No Action')))->setAttribute('value', 'no_action')
]);
$select_recommendation->setId('umg-filter-recommendation');

$input_username = (new CTag('input', false))
	->setAttribute('type', 'text')
	->setAttribute('placeholder', _('Username'))
	->setId('umg-filter-username');

$filters = (new CDiv())->addClass('umg-panel')->addItem([
	new CTag('h2', false, _('Filter Users')),
	(new CDiv())->addClass('umg-filter-row')->addItem([
		(new CDiv())->addClass('umg-filter')->addItem([new CTag('label', false, _('User')), $input_username]),
		(new CDiv())->addClass('umg-filter')->addItem([new CTag('label', false, _('Activity')), $select_activity]),
		(new CDiv())->addClass('umg-filter')->addItem([new CTag('label', false, _('Recommendation')), $select_recommendation]),
		(new CButton('umg-filter-reset', _('Reset')))
	])
]);

/*
 * ------------------------------------------------------------
 * Results table
 * ------------------------------------------------------------
 */
$thead = new CTag('thead', false, new CTag('tr', false, [
	new CTag('th', false, (new CCheckBox('umg-select-all'))),
	new CTag('th', false, _('User')),
	new CTag('th', false, _('Account Created')),
	new CTag('th', false, _('Last Login')),
	new CTag('th', false, _('Account Age')),
	new CTag('th', false, _('Inactive For')),
	new CTag('th', false, _('Activity')),
	new CTag('th', false, _('Recommendation')),
	new CTag('th', false, _('Action'))
]));

$rows = [];

foreach ($data['users'] as $user) {
	$creation_str = $user['creation_clock'] !== null
		? zbx_date2str(DATE_TIME_FORMAT_SECONDS, $user['creation_clock'])
		: _('Not found');

	$login_str = $user['last_login_clock'] !== null
		? zbx_date2str(DATE_TIME_FORMAT_SECONDS, $user['last_login_clock'])
		: _('Never');

	$account_age = $user['creation_age_days'] !== null
		? _n('%1$s day', '%1$s days', $user['creation_age_days'])
		: '—';

	$inactive_for = $user['last_login_age_days'] !== null
		? _n('%1$s day', '%1$s days', $user['last_login_age_days'])
		: '—';

	switch ($user['reason']) {
		case 'never_logged_in':
			$activity_badge = (new CSpan(_('Never Logged In')))->addClass('umg-badge umg-badge-danger');
			break;
		case 'inactive':
			$activity_badge = (new CSpan(_('Inactive')))->addClass('umg-badge umg-badge-danger');
			break;
		case 'new_account':
			$activity_badge = (new CSpan(_('New Account')))->addClass('umg-badge umg-badge-info');
			break;
		default:
			$activity_badge = (new CSpan(_('Active')))->addClass('umg-badge umg-badge-ok');
	}

	if ($user['pending_approval']) {
		$rec_badge = (new CSpan(_('Pending Approval')))->addClass('umg-badge umg-badge-warning');
	}
	elseif ($user['recommendation'] === 'disable') {
		$rec_badge = (new CSpan(_('Disable')))->addClass('umg-badge umg-badge-danger');
	}
	else {
		$rec_badge = (new CSpan(_('No Action')))->addClass('umg-badge umg-badge-ok');
	}

	if ($user['recommendation'] === 'disable' && !$user['pending_approval']) {
		$action_cell = (new CButton('', _('Disable')))
			->addClass('umg-row-disable-btn')
			->setAttribute('data-userid', $user['userid'])
			->setAttribute('data-username', $user['username']);
	}
	else {
		$action_cell = '—';
	}

	$row = new CTag('tr', false, [
		new CTag('td', false, ($user['recommendation'] === 'disable' && !$user['pending_approval'])
			? (new CCheckBox('umg-row-select[]', $user['userid']))->addClass('umg-row-checkbox')
			: ''),
		new CTag('td', false, [
			(new CDiv($user['username']))->addClass('umg-username'),
			(new CDiv(_('User ID:') . ' ' . $user['userid']))->addClass('umg-subtext')
		]),
		new CTag('td', false, $creation_str),
		new CTag('td', false, $login_str),
		new CTag('td', false, $account_age),
		new CTag('td', false, $inactive_for),
		new CTag('td', false, $activity_badge),
		new CTag('td', false, $rec_badge),
		new CTag('td', false, $action_cell)
	]);

	$row->setAttribute('data-userid', $user['userid']);
	$row->setAttribute('data-username', mb_strtolower($user['username']));
	$row->setAttribute('data-activity', $user['reason']);
	$row->setAttribute('data-recommendation', $user['recommendation']);

	$rows[] = $row;
}

$table = (new CTag('table', false, [$thead, new CTag('tbody', false, $rows)]))
	->addClass('umg-table')
	->setId('umg-table');

$results_panel = (new CDiv())->addClass('umg-panel')->addItem([
	(new CDiv())->addClass('umg-results-header')->addItem([
		new CTag('h2', false, _('Inactive User Review')),
		(new CTag('span', false, ''))->setId('umg-match-count')
	]),
	$table,
	(new CDiv())->addClass('umg-footer')->addItem([
		(new CDiv(''))->setId('umg-footer-info')->addClass('umg-card-title'),
		(new CDiv())->addClass('umg-bulk-actions')->addItem([
			(new CButton('umg-flag-selected', _('Flag Selected for Approval'))),
			(new CButton('umg-disable-selected', _('Disable Selected Users')))->addClass('btn-alt')
		])
	])
]);

/*
 * ------------------------------------------------------------
 * Policy panel (thresholds are configurable)
 * ------------------------------------------------------------
 */
$policy_panel = (new CDiv())->addClass('umg-panel')->addItem([
	new CTag('h2', false, _('Inactivity Policy (configurable)')),
	(new CDiv())->addClass('umg-filter-row')->addItem([
		(new CDiv())->addClass('umg-filter')->addItem([
			new CTag('label', false, _('Minimum Account Age (days)')),
			(new CTag('input', false))->setAttribute('type', 'number')->setAttribute('min', '0')
				->setAttribute('value', (string) $config['min_account_age_days'])->setId('umg-cfg-min-age')
		]),
		(new CDiv())->addClass('umg-filter')->addItem([
			new CTag('label', false, _('Inactivity Threshold (days)')),
			(new CTag('input', false))->setAttribute('type', 'number')->setAttribute('min', '0')
				->setAttribute('value', (string) $config['inactivity_threshold_days'])->setId('umg-cfg-threshold')
		]),
		(new CButton('umg-cfg-save', _('Save Policy')))
	])
]);

/*
 * ------------------------------------------------------------
 * Disable confirmation modal
 * ------------------------------------------------------------
 */
$modal = (new CDiv())->addClass('umg-modal-backdrop')->setId('umg-modal-backdrop')->addItem(
	(new CDiv())->addClass('umg-modal')->addItem([
		new CTag('h3', false, _('Disable Users')),
		(new CTag('div', false, ''))->setId('umg-modal-userlist')->addClass('umg-card-title'),
		new CTag('label', false, _('Request No. / Comment (required to disable immediately)')),
		(new CTag('textarea', false))->setAttribute('rows', '3')->setId('umg-modal-comment'),
		(new CDiv())->addClass('umg-modal-actions')->addItem([
			(new CButton('umg-modal-cancel', _('Cancel'))),
			(new CButton('umg-modal-flag', _('Flag for Approval'))),
			(new CButton('umg-modal-confirm', _('Disable Now')))->addClass('btn-alt')
		])
	])
);

/*
 * ------------------------------------------------------------
 * Client-side behaviour (encode=false; filtering, selection,
 * modal, AJAX to user.policy.execute / user.policy.config —
 * both controllers have CSRF disabled so no token plumbing).
 * ------------------------------------------------------------
 */
$js = <<<'JS'
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
	if (selectAll) {
		selectAll.addEventListener('change', function () {
			table.querySelectorAll('.umg-row-checkbox').forEach(function (cb) {
				var row = cb.closest('tr');
				if (!row.classList.contains('umg-row-hidden')) cb.checked = selectAll.checked;
			});
		});
	}

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
JS;

$script_tag = new CTag('script', false, $js);

(new CHtmlPage())
	->setTitle($data['title'])
	->addItem([
		$style_tag,
		$cards,
		$filters,
		$results_panel,
		$policy_panel,
		$modal,
		$script_tag
	])
	->show();
