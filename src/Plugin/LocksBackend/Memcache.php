<?php

namespace GAEDrive\Plugin\LocksBackend;

use Memcache as NativeMemcache;
use Sabre\DAV\Locks\Backend\AbstractBackend;
use Sabre\DAV\Locks\LockInfo;

/**
 * Stores all locking information in a single Memcache key
 */
class Memcache extends AbstractBackend
{
    /**
     * @var NativeMemcache
     */
    protected $memcache;

    /**
     * @param NativeMemcache $memcache
     */
    public function __construct(NativeMemcache $memcache)
    {
        $this->memcache = $memcache;
    }

    /**
     * @param string $uri
     * @param bool   $returnChildLocks
     *
     * @return array
     */
    public function getLocks($uri, $returnChildLocks)
    {
        $newLocks = [];

        $locks = $this->getData();
        foreach ($locks as $lock) {
            if (
                $lock->uri === $uri ||
                ($lock->depth != 0 && strpos($uri, $lock->uri . '/') === 0) ||
                ($returnChildLocks && (strpos($lock->uri, $uri . '/') === 0))) {
                $newLocks[] = $lock;
            }
        }

        foreach ($newLocks as $k => $lock) {
            if (time() > $lock->timeout + $lock->created) unset($newLocks[$k]);
        }

        return $newLocks;
    }

    /**
     * @return array
     */
    protected function getData()
    {
        $locks = $this->memcache->get('locks');
        if ($locks === null) {
            return [];
        }

        $data = unserialize($locks);
        if (!$data) {
            return [];
        }

        return $data;
    }

    /**
     * @param string   $uri
     * @param LockInfo $lockInfo
     *
     * @return bool
     */
    public function lock($uri, LockInfo $lockInfo)
    {
        $lockInfo->timeout = 3600;
        $lockInfo->created = time();
        $lockInfo->uri = $uri;

        if ($this->writeLock()) {
            $locks = $this->getData();
            foreach ($locks as $k => $lock) {
                if (
                    ($lock->token == $lockInfo->token) ||
                    (time() > $lock->timeout + $lock->created)
                ) {
                    unset($locks[$k]);
                }
            }

            $locks[] = $lockInfo;
            $this->putData($locks);
            $this->releaseLock();
        }

        return true;
    }

    /**
     * @return bool
     */
    protected function writeLock()
    {
        $max_wait_time = ini_get('max_execution_time');
        if ($max_wait_time === 0) {
            $max_wait_time = 300;
        }

        $start = time();
        do {
            $lock = $this->memcache->get('locks_lock');

            if ($lock === false) {
                $this->memcache->set('locks_lock', time(), 0, $max_wait_time);
                break;
            }

            if ($start + $max_wait_time < time()) {
                throw new \RuntimeException('Failed to acquire index lock');
            }
        } while ($lock !== false);

        return $this->memcache->set('locks_lock', time(), $max_wait_time);
    }

    /**
     * @param array $newData
     *
     * @return void
     */
    protected function putData(array $newData)
    {
        $this->memcache->set('locks', serialize($newData));
    }

    /**
     * @return bool
     */
    protected function releaseLock()
    {
        return $this->memcache->delete('locks_lock', time());
    }

    /**
     * @param string   $uri
     * @param LockInfo $lockInfo
     *
     * @return bool
     */
    public function unlock($uri, LockInfo $lockInfo)
    {
        if ($this->writeLock()) {
            $locks = $this->getData();
            foreach ($locks as $k => $lock) {
                if ($lock->token == $lockInfo->token) {
                    unset($locks[$k]);
                    $this->putData($locks);
                    $this->releaseLock();

                    return true;
                }
            }
        }

        return false;
    }
}
