<?php

namespace GAEDrive\FS\Traits;

use Sabre;

trait ProtectedCollectionTrait
{
    use ProtectedPropertiesTrait;

    /**
     * @throws Sabre\DAV\Exception\Forbidden
     */
    public function delete()
    {
        throw new Sabre\DAV\Exception\Forbidden('Permission denied to delete node');
    }

    /**
     * @param string $name
     *
     * @throws Sabre\DAV\Exception\Forbidden
     */
    public function setName($name)
    {
        throw new Sabre\DAV\Exception\Forbidden('Permission denied to rename node');
    }
}
