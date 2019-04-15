<?php

namespace GAEDrive\Plugin\PrincipalBackend;

use GAEDrive\Plugin\AuthPlugin;
use Sabre;
use Sabre\DAV\Exception;
use Sabre\DAV\MkCol;
use Sabre\DAV\PropPatch;
use Sabre\DAVACL\PrincipalBackend\CreatePrincipalSupport;

class AuthBackend extends AbstractBackend implements CreatePrincipalSupport
{
    /**
     * @var AuthPlugin
     */
    protected $authPlugin;

    /**
     * @var string
     */
    protected $principalPrefix;

    /**
     * @var array
     */
    protected $defaultPrincipals;

    /**
     * @var array
     */
    protected $fieldMap = [
        '{DAV:}displayname'                   => [
            'field' => 'display_name',
        ],
        '{http://sabredav.org/ns}description' => [
            'field' => 'description',
        ],
    ];

    /**
     * @param AuthPlugin $authPlugin
     * @param string     $principalPrefix
     */
    public function __construct(AuthPlugin $authPlugin, $principalPrefix = 'principals')
    {
        $this->authPlugin = $authPlugin;
        $this->principalPrefix = $principalPrefix;

        $this->defaultPrincipals = [
            [
                'uri'                                 => $principalPrefix . '/groups/administrators',
                '{DAV:}displayname'                   => 'Administrators',
                '{http://sabredav.org/ns}description' => 'Users that have access to everything',
            ],
            [
                'uri'                                 => $principalPrefix . '/groups/users',
                '{DAV:}displayname'                   => 'Users',
                '{http://sabredav.org/ns}description' => 'Users that have access to their own private directory and also shared and public directories',
            ],
            [
                'uri'                                 => $principalPrefix . '/groups/guests',
                '{DAV:}displayname'                   => 'Guests',
                '{http://sabredav.org/ns}description' => 'Users that can only access shared and public directories',
            ],
            [
                'uri'                                 => $principalPrefix . '/groups/limited_users',
                '{DAV:}displayname'                   => 'Limited Users',
                '{http://sabredav.org/ns}description' => 'Users that have only access to their own private directory and public directories',
            ],
            [
                'uri'                                 => $principalPrefix . '/groups/read_only_users',
                '{DAV:}displayname'                   => 'Read-Only Users',
                '{http://sabredav.org/ns}description' => 'Users that are only allowed to read',
            ],
        ];
    }

    /**
     * @param string $prefixPath
     * @param array  $searchProperties
     * @param string $test
     *
     * @return array
     */
    public function searchPrincipals($prefixPath, array $searchProperties, $test = 'test')
    {
        if (count($searchProperties) === 0) {
            return [];
        }

        $properties = [];
        foreach ($searchProperties as $property => $value) {
            if ($property === '{DAV:}displayname') {
                $properties[] = 'name';
            } else {
                return [];
            }
        }

        $principals = $this->getPrincipalsByPrefix(null);

        $matched_principals = [];
        foreach ($principals as $principal) {
            foreach ($properties as $property) {
                if ($property === 'name' && stripos($principal['uri'], $test) !== false) {
                    $matched_principals[] = $principal['uri'];
                }
            }
        }

        return $matched_principals;
    }

    /**
     * @param string $prefixPath
     *
     * @return array
     */
    public function getPrincipalsByPrefix($prefixPath)
    {
        $users = $this->authPlugin->getUsers();

        $principals = $this->defaultPrincipals;
        foreach ($users as $user => $data) {
            $principal = [
                'uri' => $this->principalPrefix . '/users/' . $user,
            ];

            foreach ($this->fieldMap as $field => $value) {
                if (isset($data[$value['field']]) && $data[$value['field']] !== null) {
                    $principal[$field] = $data[$value['field']];
                }
            }

            $principals[] = $principal;
        }

        $matched_principals = [];
        foreach ($principals as $principal) {
            if (empty($prefixPath) || strpos($principal['uri'], $prefixPath) !== false) {
                $matched_principals[] = $principal;
            }
        }

        return $matched_principals;
    }

    /**
     * @param string $principal
     *
     * @return array
     * @throws Exception
     */
    public function getGroupMemberSet($principal)
    {
        $principal_array = $this->getPrincipalByPath($principal);
        if (!$principal_array) {
            throw new Exception('Principal not found');
        }

        $principals = $this->getPrincipalsByPrefix(null);

        $matched_users = [];
        foreach ($principals as $this_principal) {
            $user_groups = $this->getGroupMembership($this_principal['uri']);

            foreach ($user_groups as $group) {
                if ($principal_array['uri'] === $group) {
                    $matched_users[] = $this_principal['uri'];
                }
            }
        }

        return $matched_users;
    }

    /**
     * @param string $path
     *
     * @return array
     */
    public function getPrincipalByPath($path)
    {
        $principals = $this->getPrincipalsByPrefix(null);

        foreach ($principals as $principal) {
            if ($principal['uri'] === $path) {
                return $principal;
            }
        }

        return [];
    }

    /**
     * @param string $principal
     *
     * @return array
     * @throws Exception
     */
    public function getGroupMembership($principal)
    {
        $principal_array = $this->getPrincipalByPath($principal);
        if (!$principal_array) {
            throw new Exception('Principal not found');
        }

        $users = $this->authPlugin->getUsers();

        $groups = [];
        foreach ($users as $user => $data) {
            if ($principal_array['uri'] === $this->principalPrefix . '/users/' . $user) {
                if (isset($data['is_guest']) && $data['is_guest'] === true) {
                    $groups[] = $this->principalPrefix . '/groups/guests';
                } elseif (isset($data['is_limited']) && $data['is_limited'] === true) {
                    $groups[] = $this->principalPrefix . '/groups/limited_users';
                } else {
                    $groups[] = $this->principalPrefix . '/groups/users';
                }

                if (isset($data['is_read_only']) && $data['is_read_only'] === true) {
                    $groups[] = $this->principalPrefix . '/groups/read_only_users';
                }

                if (isset($data['is_administrator']) && $data['is_administrator'] === true) {
                    $groups[] = $this->principalPrefix . '/groups/administrators';
                }
            }
        }

        return $groups;
    }

    /**
     * @param string $path
     * @param MkCol  $mkCol
     *
     * @return bool
     * @throws Sabre\DAV\Exception\MethodNotAllowed
     */
    public function createPrincipal($path, MkCol $mkCol)
    {
        $display_name = null;
        foreach ($mkCol->getMutations() as $mutation => $value) {
            if ($mutation === '{DAV:}displayname' && !empty($value)) {  // Allow only display name to be passed when creating new principal
                $display_name = $value;
            }
        }

        $result = $this->authPlugin->createUser(basename($path), $display_name);
        if (!$result) {
            return false;
        }

        return true;
    }

    /**
     * @param string    $path
     * @param PropPatch $propPatch
     *
     * @return void
     */
    public function updatePrincipal($path, PropPatch $propPatch)
    {
        $propPatch->handle(array_keys($this->fieldMap), function ($properties) use ($path) {
            $values = [];
            foreach ($properties as $key => $value) {
                if (!empty($value) && isset($this->fieldMap[$key]['field'])) {
                    $values[$this->fieldMap[$key]['field']] = $value;
                }
            }

            $result = $this->authPlugin->updateUser(basename($path), $values);
            if (!$result) {
                return false;
            }

            return true;
        });
    }

    /**
     * @param string $uri
     * @param string $principalPrefix
     *
     * @return void
     * @throws Sabre\DAV\Exception\NotImplemented
     */
    public function findByUri($uri, $principalPrefix)
    {
        throw new Sabre\DAV\Exception\NotImplemented('Not implemented');
    }

    /**
     * @param string $principal
     * @param array  $members
     *
     * @return void
     * @throws Sabre\DAV\Exception\NotImplemented
     */
    public function setGroupMemberSet($principal, array $members)
    {
        throw new Sabre\DAV\Exception\NotImplemented('Not implemented');
    }

    /**
     * @param $principal
     *
     * @return bool
     * @throws Exception
     */
    public function isAdministrator($principal = null)
    {
        empty($principal) && $principal = $this->getCurrentPrincipal();

        $principal_data = $this->getPrincipalProperties($principal);

        return isset($principal_data['is_administrator']) && $principal_data['is_administrator'] === true;
    }

    /**
     * @return string|null
     */
    public function getCurrentPrincipal()
    {
        return $this->authPlugin->getCurrentPrincipal();
    }

    /**
     * @param string $principal
     *
     * @return array
     * @throws Exception
     */
    private function getPrincipalProperties($principal)
    {
        if (empty($principal)) {
            return [];
        }

        $principal_array = $this->getPrincipalByPath($principal);
        if (!$principal_array) {
            throw new Exception('Principal not found');
        }

        $users = $this->authPlugin->getUsers();
        foreach ($users as $user => $data) {
            if ($principal_array['uri'] === $this->principalPrefix . '/users/' . $user) {
                return $data;
            }
        }

        return [];
    }

    /**
     * @param $principal
     *
     * @return bool
     * @throws Exception
     */
    public function isGuest($principal = null)
    {
        empty($principal) && $principal = $this->getCurrentPrincipal();

        $principal_data = $this->getPrincipalProperties($principal);

        return isset($principal_data['is_guest']) && $principal_data['is_guest'] === true;
    }

    /**
     * @param $principal
     *
     * @return bool
     * @throws Exception
     */
    public function isLimited($principal = null)
    {
        empty($principal) && $principal = $this->getCurrentPrincipal();

        $principal_data = $this->getPrincipalProperties($principal);

        return isset($principal_data['is_limited']) && $principal_data['is_limited'] === true;
    }

    /**
     * @param $principal
     *
     * @return bool
     * @throws Exception
     */
    public function isReadOnly($principal = null)
    {
        empty($principal) && $principal = $this->getCurrentPrincipal();

        $principal_data = $this->getPrincipalProperties($principal);

        return isset($principal_data['is_read_only']) && $principal_data['is_read_only'] === true;
    }
}
