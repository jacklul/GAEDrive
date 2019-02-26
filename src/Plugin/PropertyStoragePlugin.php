<?php

namespace GAEDrive\Plugin;

use Sabre\DAV\INode;
use Sabre\DAV\PropertyStorage\Plugin as BasePropertyStoragePlugin;
use Sabre\DAV\PropFind;
use Sabre\DAV\PropPatch;

/**
 * @property bool debug_find_sent
 * @property bool debug_patch_sent
 */
class PropertyStoragePlugin extends BasePropertyStoragePlugin
{
    /**
     * @return array
     */
    public function getPluginInfo()
    {
        return [
            'name'        => $this->getPluginName(),
            'description' => 'Allows any arbitrary WebDAV property to be set on any resource',
            'link'        => 'http://sabre.io/dav/property-storage/',
        ];
    }

    /**
     * @param PropFind $propFind
     * @param INode    $node
     */
    public function propFind(PropFind $propFind, INode $node)
    {
        parent::propFind($propFind, $node);

        if (!isset($this->debug_find_sent)) {
            syslog(LOG_INFO, print_r($propFind->getRequestedProperties(), true));
            $this->debug_find_sent = true;
        }
    }

    /**
     * @param string    $path
     * @param PropPatch $propPatch
     */
    public function propPatch($path, PropPatch $propPatch)
    {
        parent::propPatch($path, $propPatch);

        if (!isset($this->debug_patch_sent)) {
            syslog(LOG_INFO, print_r($propPatch->getMutations(), true));
            $this->debug_patch_sent = true;
        }
    }
}
