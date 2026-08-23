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
		return _('Not Found');
	}

	return zbx_date2str(
		DATE_TIME_FORMAT_SECONDS,
		$timestamp
	);
}


/*
 * ============================================================
 * Summary values
 * ============================================================
 */

$summary = $data['summary'];


/*
 * ============================================================
 * SUMMARY TABLE
 * ============================================================
 */

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
		_('Accounts Older Than '.$data['account_age_threshold'].' Days'),
		$summary['users_over_threshold']
	])

	->addRow([
		_('Accounts Younger Than '.$data['account_age_threshold'].' Days'),
		$summary['users_under_threshold']
	])

	->addRow([
		_('Creation Time Not Found'),
		$summary['users_creation_unknown']
	])

	->addRow([
		_('Pending Activity Evaluation'),
		$summary['pending_activity_check']
	])

	->addRow([
		_('Recommended Disable'),
		$summary['recommended_disable']
	]);


/*
 * ============================================================
 * POLICY INFORMATION
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
		_('Accounts Younger Than Threshold'),
		_('No Action')
	])

	->addRow([
		_('Accounts Older Than Threshold'),
		_('Evaluate Login Activity')
	])

	->addRow([
		_('Never Logged In'),
		_('To be evaluated in next phase')
	])

	->addRow([
		_('Last Login Older Than 45 Days'),
		_('To be evaluated in next phase')
	])

	->addRow([
		_('Creation Time Not Found'),
		_('No Automatic Action')
	]);


/*
 * ============================================================
 * CANDIDATE USERS TABLE
 * ============================================================
 *
 * These are users older than 60 days.
 *
 * IMPORTANT:
 *
 * They are NOT yet considered inactive.
 *
 * We still need to inspect login activity.
 */

$candidate_table = (new CTableInfo())
	->setHeader([
		_('User ID'),
		_('Username'),
		_('Name'),
		_('Surname'),
		_('Role ID'),
		_('Account Created'),
		_('Account Age'),
		_('Evaluation'),
		_('Recommendation')
	]);


foreach ($data['candidate_users'] as $user) {

	$candidate_table->addRow([
		$user['userid'],

		$user['username'],

		$user['name'],

		$user['surname'],

		$user['roleid'],

		userMgmtFormatTimestamp(
			$user['creation_clock']
		),

		$user['account_age_days'].' '._('Days'),

		_('Login Activity Check Required'),

		_('Pending')
	]);
}


/*
 * ============================================================
 * USERS UNDER 60 DAYS
 * ============================================================
 */

$new_users_table = (new CTableInfo())
	->setHeader([
		_('User ID'),
		_('Username'),
		_('Name'),
		_('Account Created'),
		_('Account Age'),
		_('Evaluation'),
		_('Recommendation')
	]);


foreach ($data['users_under_threshold'] as $user) {

	$new_users_table->addRow([
		$user['userid'],

		$user['username'],

		$user['name'],

		userMgmtFormatTimestamp(
			$user['creation_clock']
		),

		$user['account_age_days'].' '._('Days'),

		_('New Account'),

		_('No Action')
	]);
}


/*
 * ============================================================
 * CREATION TIME UNKNOWN
 * ============================================================
 */

$unknown_table = (new CTableInfo())
	->setHeader([
		_('User ID'),
		_('Username'),
		_('Name'),
		_('Role ID'),
		_('Creation Time'),
		_('Evaluation'),
		_('Recommendation')
	]);


foreach ($data['users_creation_unknown'] as $user) {

	$unknown_table->addRow([
		$user['userid'],

		$user['username'],

		$user['name'],

		$user['roleid'],

		_('Not Found'),

		_('Unable to determine account age'),

		_('No Automatic Action')
	]);
}


/*
 * ============================================================
 * MAIN PAGE
 * ============================================================
 */

$page = new CHtmlPage();

$page->setTitle(
	$data['title']
);


/*
 * ------------------------------------------------------------
 * Summary
 * ------------------------------------------------------------
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
 * ------------------------------------------------------------
 * Current policy
 * ------------------------------------------------------------
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
 * ------------------------------------------------------------
 * Accounts requiring activity evaluation
 * ------------------------------------------------------------
 */

$page->addItem(
	new CTag(
		'h2',
		true,
		_('Accounts Requiring Activity Evaluation')
	)
);

$page->addItem(
	new CTag(
		'p',
		true,
		_(
			'The following enabled accounts are older than the configured '
			.'account-age threshold. Login activity will be evaluated '
			.'before any disable recommendation is made.'
		)
	);

$page->addItem(
	$candidate_table
);


/*
 * ------------------------------------------------------------
 * New accounts
 * ------------------------------------------------------------
 */

$page->addItem(
	new CTag(
		'h2',
		true,
		_('Accounts Younger Than Threshold')
	)
);

$page->addItem(
	$new_users_table
);


/*
 * ------------------------------------------------------------
 * Accounts where creation time could not be determined
 * ------------------------------------------------------------
 */

$page->addItem(
	new CTag(
		'h2',
		true,
		_('Accounts With Unknown Creation Time')
	)
);

$page->addItem(
	new CTag(
		'p',
		true,
		_(
			'These accounts are intentionally excluded from automatic '
			.'policy actions because their creation time could not be '
			.'determined from the audit log.'
		)
	)
);

$page->addItem(
	$unknown_table
);


/*
 * ------------------------------------------------------------
 * Show page
 * ------------------------------------------------------------
 */

$page->show();
