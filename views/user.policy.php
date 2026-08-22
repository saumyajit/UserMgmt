<?php

/**
 * @var CView $this
 * @var array $data
 */

$user_table = (new CTableInfo())
	->setHeader([
		_('User ID'),
		_('Username'),
		_('Name'),
		_('Surname'),
		_('Role ID')
	]);

foreach ($data['users'] as $user) {
	$user_table->addRow([
		$user['userid'],
		$user['username'],
		$user['name'],
		$user['surname'],
		$user['roleid']
	]);
}

$audit_table = (new CTableInfo())
	->setHeader([
		_('Audit ID'),
		_('User ID'),
		_('Action'),
		_('Resource Type'),
		_('Resource ID'),
		_('Resource Name'),
		_('Clock'),
		_('IP')
	]);

foreach ($data['auditlog'] as $audit) {
	$audit_table->addRow([
		$audit['auditid'],
		$audit['userid'],
		$audit['action'],
		$audit['resourcetype'],
		$audit['resourceid'],
		$audit['resourcename'],
		$audit['clock'],
		$audit['ip']
	]);
}

(new CHtmlPage())
	->setTitle($data['title'])
	->addItem([
		(new CTag('h2', true, _('Users'))),
		$user_table,

		(new CTag('h2', true, _('Latest user creation audit entry'))),
		$audit_table
	])
	->show();
