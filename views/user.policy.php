<?php

/**
 * @var CView $this
 * @var array $data
 */


/*
 * ============================================================
 * Helper
 * ============================================================
 */

function userMgmtFormatTimestamp($timestamp): string {

	if ($timestamp === null) {
		return _('Never');
	}

	return zbx_date2str(
		DATE_TIME_FORMAT_SECONDS,
		$timestamp
	);
}


/*
 * ============================================================
 * Summary
 * ============================================================
 */

$summary = $data['summary'];


$summary_table = (new CTableInfo())
	->setHeader([
		_('Metric'),
		_('Count')
	])

	->addRow([
		_('Total Enabled Users'),
		$summary['total_enabled_users']
	])

	->addRow([
		_('Accounts Older Than 60 Days'),
		$summary['users_over_threshold']
	])

	->addRow([
		_('Accounts Younger Than 60 Days'),
		$summary['users_under_threshold']
	])

	->addRow([
		_('Creation Time Not Found'),
		$summary['users_creation_unknown']
	])

	->addRow([
		_('Never Logged In'),
		$summary['never_logged_in']
	])

	->addRow([
		_('Inactive More Than 45 Days'),
		$summary['inactive_users']
	])

	->addRow([
		_('Recently Active'),
		$summary['active_users']
	])

	->addRow([
		_('Recommended Disable'),
		$summary['recommended_disable']
	]);


/*
 * ============================================================
 * Policy
 * ============================================================
 */

$policy_table = (new CTableInfo())
	->setHeader([
		_('Policy'),
		_('Current Value')
	])

	->addRow([
		_('Minimum Account Age'),
		$data['account_age_threshold'].' '._('Days')
	])

	->addRow([
		_('Inactivity Threshold'),
		$data['inactivity_threshold'].' '._('Days')
	])

	->addRow([
		_('Account < 60 Days'),
		_('No Action')
	])

	->addRow([
		_('Account >= 60 Days + Never Logged In'),
		_('Disable')
	])

	->addRow([
		_('Account >= 60 Days + Inactive > 45 Days'),
		_('Disable')
	])

	->addRow([
		_('Account >= 60 Days + Active'),
		_('No Action')
	])

	->addRow([
		_('Creation Time Not Found'),
		_('No Automatic Action')
	]);


/*
 * ============================================================
 * RECOMMENDED DISABLE
 * ============================================================
 */

$disable_table = (new CTableInfo())
	->setHeader([
		_('User ID'),
		_('Username'),
		_('Account Created'),
		_('Account Age'),
		_('Last Login'),
		_('Inactive For'),
		_('Reason'),
		_('Recommendation')
	]);


foreach ($data['recommended_disable'] as $user) {

	if ($user['evaluation'] === 'never_logged_in') {
		$reason = _('Never Logged In');
	}
	else {
		$reason = _('Last Login Older Than 45 Days');
	}

	$disable_table->addRow([
		$user['userid'],

		$user['username'],

		userMgmtFormatTimestamp(
			$user['creation_clock']
		),

		$user['account_age_days'].' '._('Days'),

		userMgmtFormatTimestamp(
			$user['last_login_clock']
		),

		$user['inactive_days'] !== null
			? $user['inactive_days'].' '._('Days')
			: _('Never'),

		$reason,

		_('DISABLE')
	]);
}


/*
 * ============================================================
 * ALL EVALUATED USERS
 * ============================================================
 */

$evaluated_table = (new CTableInfo())
	->setHeader([
		_('User ID'),
		_('Username'),
		_('Account Created'),
		_('Account Age'),
		_('Last Login'),
		_('Inactive For'),
		_('Activity Status'),
		_('Recommendation')
	]);


foreach ($data['evaluated_users'] as $user) {

	switch ($user['evaluation']) {

		case 'never_logged_in':
			$activity_status = _('Never Logged In');
			$recommendation = _('Disable');
			break;

		case 'inactive':
			$activity_status = _('Inactive');
			$recommendation = _('Disable');
			break;

		case 'active':
			$activity_status = _('Active');
			$recommendation = _('No Action');
			break;

		default:
			$activity_status = _('Unknown');
			$recommendation = _('No Action');
			break;
	}


	$evaluated_table->addRow([
		$user['userid'],

		$user['username'],

		userMgmtFormatTimestamp(
			$user['creation_clock']
		),

		$user['account_age_days'].' '._('Days'),

		userMgmtFormatTimestamp(
			$user['last_login_clock']
		),

		$user['inactive_days'] !== null
			? $user['inactive_days'].' '._('Days')
			: _('Never'),

		$activity_status,

		$recommendation
	]);
}


/*
 * ============================================================
 * NEW USERS
 * ============================================================
 */

$new_users_table = (new CTableInfo())
	->setHeader([
		_('User ID'),
		_('Username'),
		_('Account Created'),
		_('Account Age'),
		_('Recommendation')
	]);


foreach ($data['users_under_threshold'] as $user) {

	$new_users_table->addRow([
		$user['userid'],

		$user['username'],

		userMgmtFormatTimestamp(
			$user['creation_clock']
		),

		$user['account_age_days'].' '._('Days'),

		_('No Action')
	]);
}


/*
 * ============================================================
 * UNKNOWN CREATION TIME
 * ============================================================
 */

$unknown_table = (new CTableInfo())
	->setHeader([
		_('User ID'),
		_('Username'),
		_('Name'),
		_('Role ID'),
		_('Creation Time'),
		_('Recommendation')
	]);


foreach ($data['users_creation_unknown'] as $user) {

	$unknown_table->addRow([
		$user['userid'],

		$user['username'],

		$user['name'],

		$user['roleid'],

		_('Not Found'),

		_('No Automatic Action')
	]);
}


/*
 * ============================================================
 * PAGE
 * ============================================================
 */

$page = new CHtmlPage();

$page->setTitle(
	$data['title']
);


/*
 * Summary
 */

$page->addItem(
	new CTag(
		'h2',
		true,
		_('User Account Summary')
	)
);

$page->addItem(
	$summary_table
);


/*
 * Policy
 */

$page->addItem(
	new CTag(
		'h2',
		true,
		_('Current Policy')
	)
);

$page->addItem(
	$policy_table
);


/*
 * Recommended disable
 */

$page->addItem(
	new CTag(
		'h2',
		true,
		_('Users Recommended for Disable')
	)
);

if ($summary['recommended_disable'] === 0) {

	$page->addItem(
		new CTag(
			'p',
			true,
			_('No users currently meet the disable criteria.')
		)
	);
}
else {

	$page->addItem(
		$disable_table
	);
}


/*
 * All evaluated users
 */

$page->addItem(
	new CTag(
		'h2',
		true,
		_('Activity Evaluation')
	)
);

if (!$data['evaluated_users']) {

	$page->addItem(
		new CTag(
			'p',
			true,
			_('No accounts require activity evaluation.')
		)
	);
}
else {

	$page->addItem(
		$evaluated_table
	);
}


/*
 * New accounts
 */

$page->addItem(
	new CTag(
		'h2',
		true,
		_('Accounts Younger Than 60 Days')
	)
);

$page->addItem(
	$new_users_table
);


/*
 * Unknown creation time
 */

$page->addItem(
	new CTag(
		'h2',
		true,
		_('Accounts With Unknown Creation Time')
	)
);

if (!$data['users_creation_unknown']) {

	$page->addItem(
		new CTag(
			'p',
			true,
			_('All enabled users have a creation record.')
		)
	);
}
else {

	$page->addItem(
		$unknown_table
	);
}


/*
 * Display
 */

$page->show();
