<?php

namespace GAEDrive\Helper;

use Memcache as NativeMemcache;

/**
 * Provides global access to same Memcache instance
 */
class Memcache
{
    /**
     * @var NativeMemcache
     */
    protected static $memcache;

    /**
     * @param $key
     *
     * @return array|string
     */
    public static function get($key)
    {
        self::init();

        return self::$memcache->get($key);
    }

    /**
     * @param NativeMemcache|null $memcache
     *
     * @return void
     */
    public static function init(NativeMemcache $memcache = null)
    {
        if (!self::$memcache instanceof NativeMemcache) {
            if ($memcache !== null) {
                self::$memcache = $memcache;
            } else {
                self::$memcache = new NativeMemcache();
            }
        }
    }

    /**
     * @param      $key
     * @param      $value
     * @param null $ttl
     *
     * @return bool
     */
    public static function set($key, $value, $ttl = null)
    {
        self::init();

        return self::$memcache->set($key, $value, 0, $ttl);
    }

    /**
     * @param $key
     *
     * @return bool
     */
    public static function delete($key)
    {
        self::init();

        return self::$memcache->delete($key);
    }
}
