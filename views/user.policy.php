<?php

/**
 * @var CView $this
 * @var array $data
 */

(new CHtmlPage())
	->setTitle($data['title'])
	->addItem(
		(new CDiv($data['message']))
			->addClass(ZBX_STYLE_MSG_GOOD)
	)
	->show();
