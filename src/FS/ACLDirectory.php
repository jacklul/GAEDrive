<?php

namespace GAEDrive\FS;

use Sabre;
use Sabre\DAVACL\ACLTrait;

class ACLDirectory extends Directory implements Sabre\DAVACL\IACL
{
    use ACLTrait;

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
        $this->acl   = $acl;

        parent::__construct($path);
    }

    /**
     * @param string $name
     *
     * @return ACLDirectory|AclFile|Directory|File|Sabre\DAV\INode
     * @throws Sabre\DAV\Exception\Forbidden
     * @throws Sabre\DAV\Exception\NotFound
     */
    public function getChild($name)
    {
        $path = $this->path . '/' . $name;

        if (!file_exists($path)) {
            throw new Sabre\DAV\Exception\NotFound('File could not be located');
        }

        if ($name === '.' || $name === '..') {
            throw new Sabre\DAV\Exception\Forbidden('Permission denied to . and ..');
        }

        if (is_dir($path)) {
            return new self($path, $this->owner, $this->acl);
        }

        return new ACLFile($path, $this->owner, $this->acl);
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
