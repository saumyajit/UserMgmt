<?php

/**
 * @var CView $this
 * @var array $data
 */

/*
 * ------------------------------------------------------------
 * Users table
 * ------------------------------------------------------------
 */
$user_table = (new CTableInfo())
	->setHeader([
		_('User ID'),
		_('Username'),
		_('Name'),
		_('Surname'),
		_('Role ID'),
		_('Creation Time'),
		_('Creation Epoch'),
		_('Last Login'),
		_('Last Login Epoch')
	]);

foreach ($data['users'] as $user) {
	$creation_time = $user['creation_clock'];
	$last_login_time = $user['last_login_clock'];

	$user_table->addRow([
		$user['userid'],
		$user['username'],
		$user['name'],
		$user['surname'],
		$user['roleid'],

		$creation_time !== null
			? zbx_date2str(DATE_TIME_FORMAT_SECONDS, $creation_time)
			: _('Not found'),

		$creation_time !== null
			? $creation_time
			: '',

		$last_login_time !== null
			? zbx_date2str(DATE_TIME_FORMAT_SECONDS, $last_login_time)
			: _('Never / Not found'),

		$last_login_time !== null
			? $last_login_time
			: ''
	]);
}


/*
 * ------------------------------------------------------------
 * Recent login audit records
 *
 * This is intentionally displayed during the testing phase.
 * We need to verify the actual audit record relationship
 * before finalizing the login correlation logic.
 * ------------------------------------------------------------
 */
$login_table = (new CTableInfo())
	->setHeader([
		_('Audit ID'),
		_('Audit User ID'),
		_('Audit Username'),
		_('Login Time'),
		_('Action'),
		_('Resource Type'),
		_('Resource ID'),
		_('Resource Name'),
		_('IP')
	]);

foreach ($data['login_logs'] as $login) {
	$login_table->addRow([
		$login['auditid'],
		$login['userid'],
		$login['username'],

		zbx_date2str(
			DATE_TIME_FORMAT_SECONDS,
			$login['clock']
		),

		$login['action'],
		$login['resourcetype'],
		$login['resourceid'],
		$login['resourcename'],
		$login['ip']
	]);
}


/*
 * ------------------------------------------------------------
 * Page
 * ------------------------------------------------------------
 */
(new CHtmlPage())
	->setTitle($data['title'])
	->addItem([
		(new CTag('h2', true, _('Users'))),
		$user_table,

		(new CTag('h2', true, _('Recent Successful Login Audit Records'))),
		$login_table
	])
	->show();
