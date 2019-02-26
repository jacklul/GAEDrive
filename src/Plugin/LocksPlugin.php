<?php

namespace GAEDrive\Plugin;

use Sabre\DAV\Locks\Plugin as BaseLocksPlugin;

class LocksPlugin extends BaseLocksPlugin
{
    /**
     * @return array
     */
    public function getPluginInfo()
    {
        return [
            'name'        => $this->getPluginName(),
            'description' => 'Turns this server into a class-2 WebDAV server and adds support for LOCK and UNLOCK',
            'link'        => 'http://sabre.io/dav/locks/',
        ];
    }
}
