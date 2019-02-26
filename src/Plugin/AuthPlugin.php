<?php

namespace GAEDrive\Plugin;

use GAEDrive\Plugin\AuthBackend\DatastoreBasic;
use Sabre\DAV\Auth\Plugin as BaseAuthPlugin;

/**
 * Makes it possible to run functions on first backend directly
 */
class AuthPlugin extends BaseAuthPlugin
{
    /**
     * @return array
     */
    public function getPluginInfo()
    {
        return [
            'name'        => $this->getPluginName(),
            'description' => 'Authentication plugin integrating with principals backend',
            'link'        => 'http://sabre.io/dav/authentication/',
        ];
    }

    /**
     * @return array
     */
    public function getUsers()
    {
        return $this->getFirstBackend()->getUsers();
    }

    /**
     * @return DatastoreBasic|null
     */
    protected function getFirstBackend()
    {
        foreach ($this->backends as $backend) {
            return $backend;
        }

        return null;
    }

    /**
     * @param string      $username
     * @param string|null $display_name
     *
     * @return string
     * @throws \Sabre\DAV\Exception\MethodNotAllowed
     */
    public function createUser($username, $display_name = null)
    {
        return $this->getFirstBackend()->createUser($username, $display_name);
    }

    /**
     * @param string $username
     * @param array  $data
     *
     * @return string
     * @throws \Sabre\DAV\Exception\NotFound
     */
    public function updateUser($username, $data)
    {
        return $this->getFirstBackend()->updateUser($username, $data);
    }
}
