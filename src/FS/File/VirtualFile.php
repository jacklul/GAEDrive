<?php

namespace GAEDrive\FS\File;

use GAEDrive\FS\Traits\ReadOnlyPropertiesTrait;
use Sabre;
use Sabre\DAVACL\ACLTrait;

class VirtualFile extends Sabre\DAV\SimpleFile implements Sabre\DAVACL\IACL, Sabre\DAV\IProperties
{
    use ACLTrait;
    use ReadOnlyPropertiesTrait;

    /**
     * @var bool
     */
    protected $public;

    /**
     * @param string $name
     * @param string $contents
     * @param bool   $public
     * @param string $mimeType
     */
    public function __construct($name, $contents = '', $public = false, $mimeType = 'text/plain')
    {
        $this->public = $public;

        parent::__construct($name, $contents, $mimeType);
    }

    /**
     * @return array
     */
    public function getACL()
    {
        if ($this->public === true) {
            return [
                [
                    'privilege' => '{DAV:}read',
                    'principal' => '{DAV:}all',
                    'protected' => true,
                ],
            ];
        }

        return [
            [
                'privilege' => '{DAV:}read',
                'principal' => '{DAV:}authenticated',
                'protected' => true,
            ],
        ];
    }
}
