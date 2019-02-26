<?php

namespace GAEDrive\FS\Collection;

use GAEDrive\FS\Traits\ProtectedPropertiesTrait;
use GAEDrive\Plugin\PrincipalBackend\AbstractBackend as PrincipalBackend;
use Sabre;
use Sabre\DAV\SimpleCollection;
use Sabre\DAVACL\ACLTrait;

class RootPrincipalCollection extends SimpleCollection implements Sabre\DAVACL\IACL, Sabre\DAV\IProperties
{
    use ACLTrait;
    use ProtectedPropertiesTrait;

    /**
     * @var PrincipalBackend
     */
    protected $principalBackend;

    /**
     * @param PrincipalBackend $principalBackend
     * @param string           $name
     * @param string           $principalPrefix
     * @param array            $children
     */
    public function __construct(PrincipalBackend $principalBackend, $name = 'principals', $principalPrefix = 'principals', array $children = [])
    {
        $this->principalBackend = $principalBackend;

        if (empty($children)) {
            $children = [
                new PrincipalCollection($principalBackend, $principalPrefix . '/users'),
                new PrincipalCollection($principalBackend, $principalPrefix . '/groups'),
            ];
        }

        parent::__construct($name, $children);
    }

    /**
     * @return array
     */
    public function getACL()
    {
        if (!in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
            return [];
        }

        return [
            [
                'privilege' => '{DAV:}read',
                'principal' => '{DAV:}authenticated',
                'protected' => true,
            ],
        ];
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
