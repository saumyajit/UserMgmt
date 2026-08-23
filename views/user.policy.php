<?php

/**
 * @var CView $this
 * @var array $data
 */

$policy = $data['policy'];
$counts = $data['counts'];
$filter = $data['filter'];

/*
 * ------------------------------------------------------------
 * Summary cards
 * ------------------------------------------------------------
 */
$cards = (new CDiv())
	->addClass('umg-cards')
	->addItem((new CDiv())
		->addClass('umg-card')
		->addItem([
			(new CDiv(_('Total Users')))->addClass('umg-card-title'),
			(new CDiv($data['all_users_count']))->addClass('umg-card-value'),
			(new CDiv(_('Current Zabbix users')))->addClass('umg-card-desc')
		]))
	->addItem((new CDiv())
		->addClass('umg-card')
		->addItem([
			(new CDiv(_('Never Logged In')))->addClass('umg-card-title'),
			(new CDiv($counts['never_logged_in']))->addClass('umg-card-value'),
			(new CDiv(_('No successful login on record')))->addClass('umg-card-desc')
		]))
	->addItem((new CDiv())
		->addClass('umg-card')
		->addItem([
			(new CDiv(_s('Inactive > %1$d Days', $policy['inactivity_threshold_days'])))->addClass('umg-card-title'),
			(new CDiv($counts['inactive_45']))->addClass('umg-card-value'),
			(new CDiv(_('Logged in before, gone quiet since')))->addClass('umg-card-desc')
		]))
	->addItem((new CDiv())
		->addClass('umg-card umg-card-highlight')
		->addItem([
			(new CDiv(_('Recommended Disable')))->addClass('umg-card-title'),
			(new CDiv($counts['recommend_disable']))->addClass('umg-card-value'),
			(new CDiv(_('Users meeting current policy')))->addClass('umg-card-desc')
		]));

/*
 * ------------------------------------------------------------
 * Filters
 * ------------------------------------------------------------
 */
$filters = (new CDiv())
	->addClass('umg-filters')
	->addItem((new CTag('h2', true, _('Filter Users'))))
	->addItem((new CDiv())
		->addClass('umg-filter-row')
		->addItem([
			(new CDiv())->addClass('umg-filter')->addItem([
				new CLabel(_('User'), 'filter_username'),
				(new CTextBox('filter_username', $filter['username']))->setAttribute('placeholder', _('Username'))
			]),
			(new CDiv())->addClass('umg-filter')->addItem([
				new CLabel(_('Activity'), 'filter_activity'),
				(new CSelect('filter_activity'))
					->setValue($filter['activity'])
					->addOptions(CSelect::createOptionsFromArray([
						'all' => _('All Users'),
						'never' => _('Never Logged In'),
						'logged_in' => _('Logged In'),
						'inactive' => _s('Inactive > %1$d Days', $policy['inactivity_threshold_days'])
					]))
			]),
			(new CDiv())->addClass('umg-filter')->addItem([
				new CLabel(_('Account Age'), 'filter_account_age'),
				(new CSelect('filter_account_age'))
					->setValue($filter['account_age'])
					->addOptions(CSelect::createOptionsFromArray([
						'all' => _('All'),
						'gt60' => _s('> %1$d Days', $policy['min_account_age_days']),
						'lt60' => _s('<= %1$d Days', $policy['min_account_age_days'])
					]))
			]),
			(new CDiv())->addClass('umg-filter')->addItem([
				new CLabel(_('Recommendation'), 'filter_recommendation'),
				(new CSelect('filter_recommendation'))
					->setValue($filter['recommendation'])
					->addOptions(CSelect::createOptionsFromArray([
						'all' => _('All'),
						'disable' => _('Disable'),
						'no_action' => _('No Action')
					]))
			]),
			(new CDiv())->addClass('umg-filter')->addItem([
				new CLabel(_('Status'), 'filter_status'),
				(new CSelect('filter_status'))
					->setValue($filter['status'])
					->addOptions(CSelect::createOptionsFromArray([
						'enabled' => _('Enabled'),
						'disabled' => _('Disabled'),
						'all' => _('All')
					]))
			]),
			(new CSimpleButton(_('Apply')))->addClass('btn-primary')->setId('umg-filter-apply'),
			(new CSimpleButton(_('Reset')))->setId('umg-filter-reset')
		]));

/*
 * ------------------------------------------------------------
 * Results table
 * ------------------------------------------------------------
 */
$table = (new CTable())
	->addClass('umg-table')
	->setHeader([
		(new CColHeader(new CCheckBox('select_all')))->addClass('umg-col-check'),
		_('User'),
		_('Account Created'),
		_('Last Login'),
		_('Account Age'),
		_('Inactive For'),
		_('Activity'),
		_('Recommendation'),
		_('Action')
	]);

foreach ($data['users'] as $user) {
	$creation_display = $user['creation_clock'] !== null
		? zbx_date2str(DATE_TIME_FORMAT_SECONDS, $user['creation_clock'])
		: _('Not found');

	$login_display = $user['last_login_clock'] !== null
		? zbx_date2str(DATE_TIME_FORMAT_SECONDS, $user['last_login_clock'])
		: (new CSpan(_('Never')))->addClass('umg-status umg-status-warning');

	$activity_badge = $user['last_login_clock'] === null
		? (new CSpan(_('Never Logged In')))->addClass('umg-status umg-status-danger')
		: ($user['inactive_days'] > $policy['inactivity_threshold_days']
			? (new CSpan(_('Inactive')))->addClass('umg-status umg-status-danger')
			: (new CSpan(_('Active')))->addClass('umg-status umg-status-ok'));

	if ($user['account_age_days'] !== null && $user['account_age_days'] <= $policy['min_account_age_days']
			&& $user['last_login_clock'] === null) {
		$activity_badge = (new CSpan(_('New Account')))->addClass('umg-status umg-status-info');
	}

	$recommendation_badge = $user['recommendation'] === 'disable'
		? (new CSpan(_('Disable')))->addClass('umg-status umg-status-danger')
		: (new CSpan(_('No Action')))->addClass('umg-status umg-status-ok');

	if ($user['is_disabled']) {
		$recommendation_badge = (new CSpan(_('Already Disabled')))->addClass('umg-status umg-status-info');
	}
	elseif ($user['pending_approval']) {
		$recommendation_badge = (new CSpan(_('Pending Approval')))->addClass('umg-status umg-status-warning');
	}

	$action_cell = '—';
	if (!$user['is_disabled'] && !$user['pending_approval']) {
		$action_cell = (new CSimpleButton(_('Disable')))
			->addClass('btn-danger umg-row-disable')
			->setAttribute('data-userid', $user['userid'])
			->setAttribute('data-username', $user['username']);
	}

	$table->addRow([
		(new CCheckBox('userids[' . $user['userid'] . ']'))
			->setChecked(false)
			->addClass('umg-row-check')
			->setAttribute('data-userid', $user['userid'])
			->setEnabled(!$user['is_disabled'] && !$user['pending_approval']),
		(new CDiv())->addItem([
			(new CDiv($user['username']))->addClass('umg-username'),
			(new CDiv(_s('User ID: %1$s', $user['userid'])))->addClass('umg-subtext')
		]),
		$creation_display,
		$login_display,
		$user['account_age_days'] !== null ? _s('%1$d days', $user['account_age_days']) : '—',
		$user['inactive_days'] !== null ? _s('%1$d days', $user['inactive_days']) : '—',
		$activity_badge,
		$recommendation_badge,
		$action_cell
	]);
}

$results = (new CDiv())
	->addClass('umg-results')
	->addItem((new CDiv())
		->addClass('umg-results-header')
		->addItem((new CTag('h2', true, _('Inactive User Review'))))
		->addItem((new CSpan(_s('%1$d users match the current filters', count($data['users']))))->setId('umg-match-count')))
	->addItem($table)
	->addItem((new CDiv())
		->addClass('umg-footer')
		->addItem((new CDiv(_s('Showing %1$d users', count($data['users']))))->addClass('umg-footer-info'))
		->addItem((new CDiv())
			->addClass('umg-bulk-actions')
			->addItem((new CSimpleButton(_('Export CSV')))->setId('umg-export-csv'))
			->addItem((new CSimpleButton(_('Flag Selected for Approval')))->setId('umg-bulk-flag'))
			->addItem((new CSimpleButton(_('Disable Selected Users')))->addClass('btn-danger')->setId('umg-bulk-disable'))));

/*
 * ------------------------------------------------------------
 * Policy panel
 * ------------------------------------------------------------
 */
$policy_panel = (new CDiv())
	->addClass('umg-policy')
	->addItem((new CTag('h2', true, _('Current Inactivity Policy'))))
	->addItem((new CDiv())
		->addClass('umg-policy-grid')
		->addItem([
			(new CDiv())->addClass('umg-policy-item')->addItem([
				(new CDiv(_('Minimum Account Age')))->addClass('umg-policy-label'),
				(new CDiv(_s('%1$d Days', $policy['min_account_age_days'])))->addClass('umg-policy-value')
			]),
			(new CDiv())->addClass('umg-policy-item')->addItem([
				(new CDiv(_('Inactivity Threshold')))->addClass('umg-policy-label'),
				(new CDiv(_s('%1$d Days', $policy['inactivity_threshold_days'])))->addClass('umg-policy-value')
			]),
			(new CDiv())->addClass('umg-policy-item')->addItem([
				(new CDiv(_('Never Logged In')))->addClass('umg-policy-label'),
				(new CDiv(_s('Disable if account > %1$d days', $policy['min_account_age_days'])))->addClass('umg-policy-value')
			])
		]));

/*
 * ------------------------------------------------------------
 * Disable / approval modal (hidden by default, driven by JS)
 * ------------------------------------------------------------
 */
$modal = (new CDiv())
	->setId('umg-modal-backdrop')
	->addClass('umg-modal-backdrop')
	->addItem((new CDiv())
		->addClass('umg-modal')
		->addItem((new CTag('h3', true, _('Confirm Action')))->setId('umg-modal-title'))
		->addItem((new CDiv(_('This will disable login access for the selected user(s).')))->setId('umg-modal-desc')->addClass('umg-modal-desc'))
		->addItem((new CDiv())->addClass('umg-filter')->addItem([
			new CLabel(_('Request No.'), 'umg_request_no'),
			(new CTextBox('umg_request_no', ''))->setId('umg_request_no')->setAttribute('placeholder', _('e.g. CHG0012345'))
		]))
		->addItem((new CDiv())->addClass('umg-filter')->addItem([
			new CLabel(_('Comment') . ' *', 'umg_comment'),
			(new CTextArea('umg_comment', ''))->setId('umg_comment')->setAttribute('placeholder', _('Reason for this action (required)'))
		]))
		->addItem((new CDiv())
			->addClass('umg-modal-actions')
			->addItem((new CSimpleButton(_('Cancel')))->setId('umg-modal-cancel'))
			->addItem((new CSimpleButton(_('Flag for Approval')))->setId('umg-modal-flag'))
			->addItem((new CSimpleButton(_('Disable Now')))->addClass('btn-danger')->setId('umg-modal-confirm'))));

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
.umg-results-header { padding: 14px 18px; border-bottom: 1px solid #d9dde3; display: flex; justify-content: space-between; align-items: center; }
.umg-results { overflow-x: auto; }
table.umg-table { width: 100%; table-layout: auto; border-collapse: collapse; }
table.umg-table th, table.umg-table td { padding: 10px 12px; border-bottom: 1px solid #e7e9ec; text-align: left; vertical-align: middle; white-space: nowrap; }
table.umg-table th { background: #f6f7f9; font-size: 12px; color: #4b5563; font-weight: 600; }
table.umg-table td:nth-child(2) { white-space: normal; min-width: 160px; }
table.umg-table .umg-col-check { width: 34px; }
.umg-username { font-weight: 600; display: block; }
.umg-subtext { font-size: 11px; color: #7b8490; display: block; margin-top: 2px; }
.umg-status { display: inline-block; padding: 3px 8px; border-radius: 3px; font-size: 11px; font-weight: 600; }
.umg-status-danger { background: #fde8e8; color: #b42323; }
.umg-status-warning { background: #fff4d6; color: #8a6200; }
.umg-status-ok { background: #e6f6ed; color: #176b3a; }
.umg-status-info { background: #e8f1fb; color: #175a9d; }
.umg-footer { padding: 14px 18px; display: flex; justify-content: space-between; align-items: center; border-top: 1px solid #d9dde3; }
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
	var selected = {};

	function selectedIds() {
		return Object.keys(selected).filter(function(id) { return selected[id]; });
	}

	document.querySelectorAll('.umg-row-check').forEach(function(cb) {
		cb.addEventListener('change', function() {
			selected[cb.dataset.userid] = cb.checked;
		});
	});

	var selectAll = document.querySelector('[name=select_all]');
	if (selectAll) {
		selectAll.addEventListener('change', function() {
			document.querySelectorAll('.umg-row-check:not(:disabled)').forEach(function(cb) {
				cb.checked = selectAll.checked;
				selected[cb.dataset.userid] = cb.checked;
			});
		});
	}

	var modalBackdrop = document.getElementById('umg-modal-backdrop');
	var pendingIds = [];
	var pendingMode = null;

	function openModal(ids, single) {
		pendingIds = ids;
		document.getElementById('umg_request_no').value = '';
		document.getElementById('umg_comment').value = '';
		document.getElementById('umg-modal-desc').textContent =
			ids.length > 1
				? ids.length + ' users selected. Choose an action below.'
				: 'This will affect 1 user. Choose an action below.';
		modalBackdrop.classList.add('umg-open');
	}

	document.querySelectorAll('.umg-row-disable').forEach(function(btn) {
		btn.addEventListener('click', function() {
			openModal([btn.dataset.userid], true);
		});
	});

	var bulkDisableBtn = document.getElementById('umg-bulk-disable');
	if (bulkDisableBtn) {
		bulkDisableBtn.addEventListener('click', function() {
			var ids = selectedIds();
			if (ids.length === 0) { alert('Select at least one user.'); return; }
			openModal(ids, false);
		});
	}

	var bulkFlagBtn = document.getElementById('umg-bulk-flag');
	if (bulkFlagBtn) {
		bulkFlagBtn.addEventListener('click', function() {
			var ids = selectedIds();
			if (ids.length === 0) { alert('Select at least one user.'); return; }
			submitAction(ids, 'flag_approval');
		});
	}

	document.getElementById('umg-modal-cancel').addEventListener('click', function() {
		modalBackdrop.classList.remove('umg-open');
	});

	document.getElementById('umg-modal-flag').addEventListener('click', function() {
		submitAction(pendingIds, 'flag_approval');
	});

	document.getElementById('umg-modal-confirm').addEventListener('click', function() {
		var comment = document.getElementById('umg_comment').value.trim();
		if (!comment) { alert('A comment is required to disable directly.'); return; }
		submitAction(pendingIds, 'disable_now');
	});

	function submitAction(ids, mode) {
		var params = new URLSearchParams();
		ids.forEach(function(id) { params.append('userids[]', id); });
		params.append('mode', mode);
		params.append('request_no', document.getElementById('umg_request_no').value);
		params.append('comment', document.getElementById('umg_comment').value);

		fetch('zabbix.php?action=user.policy.execute', {
			method: 'POST',
			headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
			body: params.toString()
		})
		.then(function(r) { return r.json(); })
		.then(function(data) {
			modalBackdrop.classList.remove('umg-open');
			if (data.success === false) {
				alert(data.error || 'Action failed.');
			}
			else {
				location.reload();
			}
		})
		.catch(function() {
			alert('Request failed — check the network tab.');
		});
	}

	var applyBtn = document.getElementById('umg-filter-apply');
	if (applyBtn) {
		applyBtn.addEventListener('click', function() {
			var params = new URLSearchParams();
			params.append('filter_set', 1);
			params.append('filter_username', document.querySelector('[name=filter_username]').value);
			['filter_activity', 'filter_account_age', 'filter_recommendation', 'filter_status'].forEach(function(name) {
				var el = document.querySelector('[name=' + name + ']');
				if (el) { params.append(name, el.value); }
			});
			location.href = 'zabbix.php?action=user.policy&' + params.toString();
		});
	}

	var resetBtn = document.getElementById('umg-filter-reset');
	if (resetBtn) {
		resetBtn.addEventListener('click', function() {
			location.href = 'zabbix.php?action=user.policy&filter_rst=1';
		});
	}

	var exportBtn = document.getElementById('umg-export-csv');
	if (exportBtn) {
		exportBtn.addEventListener('click', function() {
			var rows = [['User ID', 'Username', 'Account Created', 'Last Login', 'Account Age (days)', 'Inactive (days)', 'Recommendation']];
			document.querySelectorAll('.umg-table tbody tr').forEach(function(tr) {
				var cells = tr.querySelectorAll('td');
				if (cells.length < 9) { return; }
				rows.push([
					cells[1].querySelector('.umg-subtext') ? cells[1].querySelector('.umg-subtext').textContent.replace('User ID: ', '') : '',
					cells[1].querySelector('.umg-username') ? cells[1].querySelector('.umg-username').textContent : '',
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
	->addItem(
		// encode = false: this must go out as raw CSS, not HTML-escaped text.
		(new CTag('style', false, $css))
	)
	->addItem($cards)
	->addItem($filters)
	->addItem($results)
	->addItem($policy_panel)
	->addItem($modal)
	// encode = false: same deal for the script block — escaping it silently
	// turned every button/filter/modal handler into dead markup.
	->addItem((new CTag('script', false, $js)))
	->show();
