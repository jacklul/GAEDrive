<?php

namespace GAEDrive\FS;

use Sabre;
use Sabre\DAV\PropPatch;

class HomeDirectory extends ACLDirectory
{
    /**
     * @param PropPatch $propPatch
     *
     * @return void
     * @throws Sabre\DAV\Exception\Forbidden
     */
    public function propPatch(PropPatch $propPatch)
    {
        throw new Sabre\DAV\Exception\Forbidden('Permission denied to set properties on this node');
    }

    /**
     * @param array $properties
     *
     * @return array
     */
    public function getProperties($properties)
    {
        return [];
    }
}
