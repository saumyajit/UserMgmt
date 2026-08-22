<?php

namespace Modules\UserMgmt;

use Zabbix\Core\CModule;
use APP;
use CMenuItem;

class Module extends CModule {

    public function init(): void {
        APP::Component()->get('menu.main')
            ->findOrAdd(_('Users'))
                ->getSubmenu()
                    ->insertAfter(_('Authentication'),
                        (new CMenuItem(_('User Mgmt')))->setAction('user.policy')
                    );
    }
}
