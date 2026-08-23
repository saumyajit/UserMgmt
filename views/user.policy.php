<?php

/**
 * @var CView $this
 * @var array $data
 */

$users_older_than_threshold = 0;
$creation_time_missing = 0;

foreach ($data['candidate_users'] as $user) {
	if ($user['candidate_reason'] === 'account_older_than_threshold') {
		$users_older_than_threshold++;
	}
	elseif ($user['candidate_reason'] === 'creation_time_not_found') {
		$creation_time_missing++;
	}
}


/*
 * ------------------------------------------------------------
 * Summary
 * ------------------------------------------------------------
 */

$summary_table = (new CTableInfo())
	->setHeader([
		_('Metric'),
		_('Value')
	])
	->addRow([
		_('Enabled Users'),
		$data['total_users']
	])
	->addRow([
		_('Accounts Older Than 60 Days'),
		$users_older_than_threshold
	])
	->addRow([
		_('Creation Time Not Found'),
		$creation_time_missing
	]);


/*
 * ------------------------------------------------------------
 * Candidate users
 * ------------------------------------------------------------
 */

$table = (new CTableInfo())
	->setHeader([
		_('User ID'),
		_('Username'),
		_('Name'),
		_('Surname'),
		_('Account Created'),
		_('Account Age'),
		_('Evaluation')
	]);


foreach ($data['candidate_users'] as $user) {

	$creation_clock = $user['creation_clock'];

	if ($creation_clock !== null) {

		$creation_time = zbx_date2str(
			DATE_TIME_FORMAT_SECONDS,
			$creation_clock
		);

		$account_age = $user['account_age_days'].' '._('days');

		if ($user['candidate_reason'] === 'account_older_than_threshold') {
			$evaluation = _('Needs Activity Check');
		}
		else {
			$evaluation = _('No Automatic Action');
		}
	}
	else {
		$creation_time = _('Not Found');
		$account_age = _('Unknown');

		$evaluation = _('Creation Time Missing - No Automatic Action');
	}

	$table->addRow([
		$user['userid'],
		$user['username'],
		$user['name'],
		$user['surname'],
		$creation_time,
		$account_age,
		$evaluation
	]);
}


/*
 * ------------------------------------------------------------
 * Policy
 * ------------------------------------------------------------
 */

$policy_table = (new CTableInfo())
	->setHeader([
		_('Policy'),
		_('Value')
	])
	->addRow([
		_('Minimum Account Age'),
		$data['account_age_threshold'].' '._('days')
	])
	->addRow([
		_('Accounts Younger Than Threshold'),
		_('No activity evaluation')
	])
	->addRow([
		_('Accounts Older Than Threshold'),
		_('Evaluate login activity')
	])
	->addRow([
		_('Creation Time Missing'),
		_('No automatic action')
	]);


/*
 * ------------------------------------------------------------
 * Page
 * ------------------------------------------------------------
 */

(new CHtmlPage())
	->setTitle($data['title'])
	->addItem(
		(new CTag('h2', true, _('Summary')))
	)
	->addItem($summary_table)
	->addItem(
		(new CTag('h2', true, _('Accounts Requiring Activity Evaluation')))
	)
	->addItem($table)
	->addItem(
		(new CTag('h2', true, _('Current Policy')))
	)
	->addItem($policy_table)
	->show();
