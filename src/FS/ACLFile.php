<?php

namespace GAEDrive\FS;

use Sabre\DAVACL\ACLTrait;

class ACLFile extends File implements \Sabre\DAVACL\IACL
{
    use AclTrait;

    /**
     * @var string
     */
    protected $owner;

    /**
     * @var array
     */
    protected $acl;

    /**
     * @param string $path
     * @param string $owner
     * @param null   $acl
     */
    public function __construct($path, $owner, $acl = null)
    {
        $this->owner = $owner;
        $this->acl = $acl;

        parent::__construct($path);
    }

    /**
     * @return null|string
     */
    public function getOwner()
    {
        return $this->owner;
    }

    /**
     * @return array|null
     */
    public function getACL()
    {
        if ($this->acl !== null) {
            return $this->acl;
        }

        return [
            [
                'privilege' => '{DAV:}all',
                'principal' => '{DAV:}owner',
                'protected' => true,
            ],
        ];
    }
}
