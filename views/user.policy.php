<?php

/**
 * @var CView $this
 * @var array $data
 */

$table = (new CTableInfo())
	->setHeader([
		_('User ID'),
		_('Username'),
		_('Name'),
		_('Surname'),
		_('Role ID')
	]);

foreach ($data['users'] as $user) {
	$table->addRow([
		$user['userid'],
		$user['username'],
		$user['name'],
		$user['surname'],
		$user['roleid']
	]);
}

(new CHtmlPage())
	->setTitle($data['title'])
	->addItem($table)
	->show();
