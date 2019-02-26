<?php

namespace GAEDrive\FS\Collection;

use GAEDrive\FS\ACLDirectory;
use GAEDrive\FS\Traits\ProtectedCollectionTrait;
use GAEDrive\Plugin\PrincipalBackend\AbstractBackend as PrincipalBackend;
use Sabre;
use Sabre\DAVACL\ACLTrait;

class SharedColletion extends ACLDirectory implements Sabre\DAV\IProperties
{
    use AclTrait;
    use ProtectedCollectionTrait;

    /**
     * @var PrincipalBackend
     */
    protected $principalBackend;

    /**
     * @param string           $path
     * @param PrincipalBackend $principalBackend
     *
     * @throws Sabre\DAV\Exception
     */
    public function __construct($path, PrincipalBackend $principalBackend = null)
    {
        $this->principalBackend = $principalBackend;

        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }

        parent::__construct($path, null, $this->getACL());
    }

    /**
     * @return array
     * @throws Sabre\DAV\Exception
     */
    public function getACL()
    {
        if ($this->principalBackend instanceof PrincipalBackend && $this->principalBackend->getCurrentPrincipal() !== null) {
            if ($this->principalBackend->isLimited()) {  // Hides this collection when user does not have access to it
                return [];
            }
        }

        return [
            [
                'privilege' => '{DAV:}all',
                'principal' => '{DAV:}authenticated',
                'protected' => true,
            ],
        ];
    }
}
