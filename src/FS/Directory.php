<?php /** @noinspection PhpDeprecationInspection */

namespace GAEDrive\FS;

use GAEDrive\Helper\Memcache;
use GAEDrive\Server;
use Sabre;
use Sabre\DAV\INode;
use Sabre\HTTP\URLUtil;

class Directory extends Node implements Sabre\DAV\ICollection, Sabre\DAV\IQuota
{
    /**
     * @param string $name
     *
     * @return void
     * @throws Sabre\DAV\Exception\ServiceUnavailable
     */
    public function setName($name)
    {
        if (is_dir($this->path . '/') && (count(scandir($this->path . '/', SCANDIR_SORT_NONE)) === 2)) {
            throw new Sabre\DAV\Exception\ServiceUnavailable('Renaming of non-empty collection is not supported');
        }

        list($parentPath,) = URLUtil::splitPath($this->path);
        list(, $newName) = URLUtil::splitPath($name);

        $newPath = $parentPath . '/' . $newName;
        $result = rename($this->path . '/', $newPath . '/');
        if (!$result) {
            throw new Sabre\DAV\Exception\ServiceUnavailable('Error while renaming node');
        }

        Memcache::delete('meta:' . sha1($this->path));
        syslog(LOG_INFO, $newPath);

        $this->path = $newPath;
    }

    /**
     * @param string          $name
     * @param resource|string $data
     *
     * @return null|string
     * @throws Sabre\DAV\Exception\Forbidden
     * @throws Sabre\DAV\Exception\ServiceUnavailable
     */
    public function createFile($name, $data = null)
    {
        // We're not allowing dots
        if ($name == '.' || $name == '..') {
            throw new Sabre\DAV\Exception\Forbidden('Permission denied to . and ..');
        }

        $newPath = $this->path . '/' . $name;
        $result = file_put_contents($newPath, $data);
        if ($result === false) {
            throw new Sabre\DAV\Exception\ServiceUnavailable('Error while writing data');
        }

        clearstatcache(true, $newPath);
        Memcache::set('quota_rescan', time());

        return '"' . sha1(filesize($newPath) . filemtime($newPath)) . '"';
    }

    /**
     * @param string $name
     *
     * @throws Sabre\DAV\Exception\Forbidden
     * @throws Sabre\DAV\Exception\ServiceUnavailable
     */
    public function createDirectory($name)
    {
        // We're not allowing dots
        if ($name == '.' || $name == '..') {
            throw new Sabre\DAV\Exception\Forbidden('Permission denied to . and ..');
        }

        $newPath = $this->path . '/' . $name;
        $result = mkdir($newPath);
        if ($result === false) {
            throw new Sabre\DAV\Exception\ServiceUnavailable('Error while creating directory');
        }

        clearstatcache(true, $newPath);
    }

    /**
     * Checks if a child exists.
     *
     * @param string $name
     *
     * @return bool
     * @throws Sabre\DAV\Exception\Forbidden
     */
    public function childExists($name)
    {
        if ($name == '.' || $name == '..') {
            throw new Sabre\DAV\Exception\Forbidden('Permission denied to . and ..');
        }

        $path = $this->path . '/' . $name;

        return file_exists($path);
    }

    /**
     * @return bool
     * @throws Sabre\DAV\Exception\Forbidden
     * @throws Sabre\DAV\Exception\NotFound
     * @throws Sabre\DAV\Exception\ServiceUnavailable
     */
    public function delete()
    {
        foreach ($this->getChildren() as $child) {
            $child->delete();
        }

        $result = rmdir($this->path);
        if ($result === false) {
            throw new Sabre\DAV\Exception\ServiceUnavailable('Error while deleting directory');
        }

        Memcache::delete('meta:' . sha1($this->path));
        Memcache::set('quota_rescan', time());

        return true;
    }

    /**
     * @return array|INode[]
     * @throws Sabre\DAV\Exception\Forbidden
     * @throws Sabre\DAV\Exception\NotFound
     */
    public function getChildren()
    {
        $nodes = [];
        $iterator = new \FilesystemIterator(
            $this->path,
            \FilesystemIterator::CURRENT_AS_SELF
            | \FilesystemIterator::SKIP_DOTS
        );

        foreach ($iterator as $entry) {
            $nodes[] = $this->getChild($entry->getFilename());
        }

        return $nodes;
    }

    /**
     * @param string $name
     *
     * @return Directory|File|INode
     * @throws Sabre\DAV\Exception\Forbidden
     * @throws Sabre\DAV\Exception\NotFound
     */
    public function getChild($name)
    {
        $path = $this->path . '/' . $name;

        if (!file_exists($path)) {
            throw new Sabre\DAV\Exception\NotFound('File could not be located');
        }

        if ($name == '.' || $name == '..') {
            throw new Sabre\DAV\Exception\Forbidden('Permission denied to . and ..');
        }

        if (is_dir($path)) {
            return new self($path);
        } else {
            return new File($path);
        }
    }

    /**
     * @return array
     */
    public function getQuotaInfo()
    {
        $quota = Memcache::get('quota');
        if (is_array($quota)) {
            return $quota;
        }

        return [0, Server::MAX_QUOTA];
    }
}