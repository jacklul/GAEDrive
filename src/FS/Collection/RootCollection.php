<?php

namespace GAEDrive\FS\Collection;

use Exception;
use GAEDrive\Plugin\AuthPlugin;
use Sabre;
use Sabre\DAVACL\ACLTrait;

class RootCollection extends Sabre\DAV\SimpleCollection implements Sabre\DAVACL\IACL
{
    use AclTrait;

    /**
     * @var bool
     */
    protected $public;

    /**
     * @var AuthPlugin
     */
    protected $authPlugin;

    /**
     * @param array      $children
     * @param AuthPlugin $authPlugin
     * @param bool       $public
     */
    public function __construct(array $children, AuthPlugin $authPlugin, $public = false)
    {
        $this->public = $public;
        $this->authPlugin = $authPlugin;

        parent::__construct('root', $children);
    }

    /**
     * @return array
     */
    public function getChildren()
    {
        $nodes = [];
        foreach ($this->children as $node) {
            if (empty($this->authPlugin->getCurrentPrincipal())) {
                if (method_exists($node, 'getACL')) {
                    try {
                        $acl = $node->getACL();
                        foreach ($acl as $acl_rule) {
                            if (isset($acl_rule['principal']) && ($acl_rule['principal'] === '{DAV:}all' || $acl_rule['principal'] === '{DAV:}unauthenticated')) {
                                $nodes[] = $node;
                            }
                        }
                    } catch (Exception $e) {
                        // Do nothing
                    }
                }
            } else {
                $nodes[] = $node;
            }
        }

        return $nodes;
    }

    /**
     * @return array
     */
    public function getACL()
    {
        if ($this->public) {
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
