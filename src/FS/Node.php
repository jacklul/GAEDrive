<?php /** @noinspection PhpDeprecationInspection */

namespace GAEDrive\FS;

use GAEDrive\Helper\Memcache;
use Sabre;
use Sabre\HTTP\URLUtil;

abstract class Node implements Sabre\DAV\INode
{
    /**
     * @var string
     */
    protected $path;

    /**
     * @param string $path
     */
    public function __construct($path)
    {
        $this->path = $path;
    }

    /**
     * @return string
     */
    public function getPath()
    {
        return $this->path;
    }

    /**
     * @return string
     */
    public function getName()
    {
        list(, $name) = URLUtil::splitPath($this->path);

        return $name;
    }

    /**
     * @param string $name
     *
     * @return void
     * @throws Sabre\DAV\Exception\ServiceUnavailable
     */
    public function setName($name)
    {
        list($parentPath,) = URLUtil::splitPath($this->path);
        list(, $newName) = URLUtil::splitPath($name);

        $newPath = $parentPath . '/' . $newName;
        $result = rename($this->path, $newPath);
        if (!$result) {
            throw new Sabre\DAV\Exception\ServiceUnavailable('Error while renaming node');
        }

        Memcache::delete('meta:' . sha1($this->path));
        syslog(LOG_INFO, $newPath);

        $this->path = $newPath;
    }

    /**
     * @return int
     */
    public function getLastModified()
    {
        $cached_meta = Memcache::get('meta:' . sha1($this->path));
        if (!is_array($cached_meta)) {
            $cached_meta = [];
        }

        if (!isset($cached_meta['last_modified'])) {
            $cached_meta['last_modified'] = filemtime($this->path);
        }

        if ($cached_meta['last_modified'] !== false && $cached_meta['last_modified'] > 0) {
            Memcache::set('meta:' . sha1($this->path), $cached_meta, 3600);
        }

        return $cached_meta['last_modified'];
    }
}
