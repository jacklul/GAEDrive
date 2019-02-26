<?php

namespace GAEDrive\FS;

use GAEDrive\Plugin\PrincipalBackend\AuthBackend;
use Sabre;

class Principal extends Sabre\DAVACL\Principal
{
    /**
     * @var AuthBackend
     */
    protected $principalBackend;

    /**
     * @return null|string
     */
    public function getOwner()
    {
        if (strpos($this->principalProperties['uri'], '/groups/') !== false) {
            return null;
        }

        return $this->principalProperties['uri'];
    }

    /**
     * @return array
     */
    public function getACL()
    {
        return [
            [
                'privilege' => '{DAV:}all',
                'principal' => '{DAV:}owner',
                'protected' => true,
            ],
        ];
    }
}
