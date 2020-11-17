<?php

namespace GAEDrive\FS\Traits;

use Sabre;
use Sabre\DAV\PropPatch;

trait ProtectedPropertiesTrait
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
