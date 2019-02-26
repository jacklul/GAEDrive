<?php

namespace GAEDrive\FS\Collection;

class PublicColletion extends SharedColletion
{
    /**
     * @return array
     * @throws \Sabre\DAV\Exception
     */
    public function getACL()
    {
        $acl = parent::getACL();
        $acl[] = [
            'privilege' => '{DAV:}read',
            'principal' => '{DAV:}unauthenticated',
            'protected' => true,
        ];

        return $acl;
    }
}
