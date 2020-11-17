<?php

namespace GAEDrive\FS\Collection;

use Sabre\DAV\Exception;

class PublicColletion extends SharedColletion
{
    /**
     * @return array
     * @throws Exception
     */
    public function getACL()
    {
        $acl   = parent::getACL();
        $acl[] = [
            'privilege' => '{DAV:}read',
            'principal' => '{DAV:}unauthenticated',
            'protected' => true,
        ];

        return $acl;
    }
}
