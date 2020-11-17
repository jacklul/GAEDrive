<?php

namespace GAEDrive\Plugin\AuthBackend;

use Google\Cloud\Datastore\DatastoreClient;
use Google\Cloud\Datastore\Entity;
use Google\Cloud\Datastore\Key;
use Memcache;
use Sabre;
use Sabre\DAV\Auth\Backend\AbstractBasic;

/**
 * Uses Google Cloud Datastore to manage users and passwords
 */
class DatastoreBasic extends AbstractBasic
{
    /**
     * @var DatastoreClient
     */
    protected $datastore;

    /**
     * @var Memcache
     */
    protected $memcache;

    /**
     * @var array
     */
    private $cache = [];

    /**
     * @param DatastoreClient $datastore
     * @param Memcache        $memcache
     * @param string          $principalPrefix
     */
    public function __construct(DatastoreClient $datastore, Memcache $memcache, $principalPrefix = 'principals')
    {
        $this->datastore       = $datastore;
        $this->memcache        = $memcache;
        $this->principalPrefix = $principalPrefix . '/users/';
    }

    /**
     * @param string      $username
     * @param string|null $display_name
     *
     * @return string
     * @throws Sabre\DAV\Exception\MethodNotAllowed
     */
    public function createUser($username, $display_name = null)
    {
        $user_key    = $this->datastore->key('User', (string) $username, ['identifierType' => Key::TYPE_NAME]);
        $user_entity = $this->datastore->lookup($user_key);

        if ($user_entity === null) {
            $user_entity                                           = $this->datastore->entity($user_key);
            $user_entity['password_hash']                          = null;
            $display_name !== null && $user_entity['display_name'] = $display_name;
            $result                                                = $this->datastore->insert($user_entity);
        } else {
            throw new Sabre\DAV\Exception\MethodNotAllowed('The resource you tried to create already exists');
        }

        $this->memcache->delete('auth_users');
        if (isset($this->cache['users'])) {
            $this->cache['users'] = null;
        }

        return $result;
    }

    /**
     * @param string $username
     *
     * @param array  $data
     *
     * @return string
     * @throws Sabre\DAV\Exception\NotFound
     */
    public function updateUser($username, $data)
    {
        $user_key    = $this->datastore->key('User', (string) $username, ['identifierType' => Key::TYPE_NAME]);
        $user_entity = $this->datastore->lookup($user_key);

        if ($user_entity !== null) {
            foreach ($data as $key => $val) {
                $user_entity[$key] = $val;
            }

            $result = $this->datastore->update($user_entity);
        } else {
            throw new Sabre\DAV\Exception\NotFound('The resource you tried to update doesn\'t exists');
        }

        $this->memcache->delete('auth_users');
        if (isset($this->cache['users'])) {
            $this->cache['users'] = null;
        }

        return $result;
    }

    /**
     * @param string $username
     * @param string $password
     *
     * @return bool
     */
    protected function validateUserPass($username, $password)
    {
        $hash  = md5($password);
        $users = $this->getUsers();

        return isset($users[$username]) && $users[$username]['password_hash'] !== null && $users[$username]['password_hash'] === $hash;
    }

    /**
     * @return array
     */
    public function getUsers()
    {
        if (isset($this->cache['users']) && $this->cache['users'] !== null) {
            return $this->cache['users'];
        }

        $users = $this->memcache->get('auth_users');
        if (!$users) {
            $query = $this->datastore->query()
                ->kind('User');
            $result = $this->datastore->runQuery($query);

            $users = [];
            if (iterator_count($result) > 0) {
                /** @var Entity $entity */
                foreach ($result as $entity) {
                    $entity_data = $entity->get();

                    if ($entity->key() !== null) {
                        $username = strtolower($entity->key()->pathEndIdentifier());

                        foreach ($entity_data as $key => $value) {
                            $users[$username][$key] = $value;
                        }
                    }
                }

                $this->memcache->set('auth_users', $users, 0, 300);
            }
        }

        $this->cache['users'] = $users;

        return $users;
    }
}
