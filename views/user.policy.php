<?php

/**
 * @var CView $this
 * @var array $data
 */

/*
 * ------------------------------------------------------------
 * Summary
 * ------------------------------------------------------------
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
 * Summary cards
 * ------------------------------------------------------------
 */

$summary = (new CDiv())
	->addClass('dashboard-grid')
	->addItem(
		(new CDiv())
			->addClass('dashboard-card')
			->addItem(
				(new CTag('div', true, _('Enabled Users')))
					->addClass('dashboard-card-title')
			)
			->addItem(
				(new CTag('div', true, $data['total_users']))
					->addClass('dashboard-card-value')
			)
	)
	->addItem(
		(new CDiv())
			->addClass('dashboard-card')
			->addItem(
				(new CTag('div', true, _('Account Age > 60 Days')))
					->addClass('dashboard-card-title')
			)
			->addItem(
				(new CTag('div', true, $users_older_than_threshold))
					->addClass('dashboard-card-value')
			)
	)
	->addItem(
		(new CDiv())
			->addClass('dashboard-card')
			->addItem(
				(new CTag('div', true, _('Creation Time Not Found')))
					->addClass('dashboard-card-title')
			)
			->addItem(
				(new CTag('div', true, $creation_time_missing))
					->addClass('dashboard-card-value')
			)
	);


/*
 * ------------------------------------------------------------
 * Candidate table
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
			$evaluation = (new CSpan(_('Needs Activity Check')))
				->addClass(ZBX_STYLE_STATUS)
				->addClass(ZBX_STYLE_STATUS_WARNING);
		}
		else {
			$evaluation = _('No automatic action');
		}
	}
	else {
		$creation_time = _('Not found');
		$account_age = _('Unknown');

		$evaluation = (new CSpan(_('Creation Time Missing')))
			->addClass(ZBX_STYLE_STATUS)
			->addClass(ZBX_STYLE_STATUS_WARNING);
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
 * Policy information
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
	->addItem($summary)
	->addItem(
		(new CTag('h2', true, _('Accounts Requiring Activity Evaluation')))
			->addClass('section-title')
	)
	->addItem($table)
	->addItem(
		(new CTag('h2', true, _('Current Policy')))
			->addClass('section-title')
	)
	->addItem($policy_table)
	->show();
