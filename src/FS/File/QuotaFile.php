<?php

namespace GAEDrive\FS\File;

use GAEDrive\Helper\Memcache;
use GAEDrive\Server;

/**
 * This file will always contain quota information
 */
class QuotaFile extends VirtualFile
{
    /**
     * @param string $name
     * @param bool   $public
     */
    public function __construct($name, $public = true)
    {
        parent::__construct($name, null, $public);
    }

    /**
     * @return int
     */
    function getSize()
    {
        return strlen($this->get());
    }

    /**
     * @return mixed
     */
    function get()
    {
        if ($this->contents !== null) {
            return $this->contents;
        }

        $quota = Memcache::get('quota');
        if (!is_array($quota)) {
            $quota = [0, Server::MAX_QUOTA];
        }

        return $this->contents =
            'Used: ' . $quota[0] . ' bytes' . PHP_EOL .
            'Available: ' . $quota[1] . ' bytes' . PHP_EOL .
            'Total: ' . ($quota[0] + $quota[1]) . ' bytes' . PHP_EOL .
            (isset($quota[3]) && isset($quota[4]) ? PHP_EOL . 'Files: ' . $quota[3] . PHP_EOL . 'Directories: ' . $quota[4] . PHP_EOL : '') .
            (isset($quota[2]) ? PHP_EOL . 'Calculated at ' . date('l, d F Y H:i:s T', $quota[2]) . PHP_EOL : '');
    }

    /**
     * @return string
     */
    function getETag()
    {
        return '"' . sha1($this->get()) . '"';
    }

    /**
     * @return int
     */
    public function getLastModified()
    {
        if ($this->contents === null) {
            $this->get();
        }

        preg_match("/Calculated at (.*)/", $this->contents, $matches);
        if (isset($matches[1])) {
            return strtotime($matches[1]);
        }

        return null;
    }
}
