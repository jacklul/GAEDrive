<?php /** @noinspection PhpDeprecationInspection */

namespace GAEDrive\FS;

use FilesystemIterator;
use GAEDrive\FS\File\VirtualFile;
use GAEDrive\Helper\Memcache;
use GAEDrive\Server;
use Sabre;
use Sabre\DAV\INode;
use Sabre\HTTP\URLUtil;

class Directory extends Node implements Sabre\DAV\ICollection, Sabre\DAV\IQuota
{
    const MAX_ENTRIES = 100;

    /**
     * @var bool
     */
    private $paginated;

    /**
     * @var int
     */
    private $pages;

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
        if ($name === '.' || $name === '..') {
            throw new Sabre\DAV\Exception\Forbidden('Permission denied to . and ..');
        }

        $newPath = $this->path . '/' . $name;
        $result = file_put_contents($newPath, $data);
        if ($result === false) {
            throw new Sabre\DAV\Exception\ServiceUnavailable('Error while writing data');
        }

        clearstatcache(true, $newPath);

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
        if ($name === '.' || $name === '..') {
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
        if ($name === '.' || $name === '..') {
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
     * @throws Sabre\DAV\Exception\NotImplemented
     */
    public function delete()
    {
        if ($this->isPaginated()) {
            throw new Sabre\DAV\Exception\NotImplemented('Directory contains more than ' . self::MAX_ENTRIES . ' entries and cannot be deleted in one operation! Remove all child elements first!');
        }

        foreach ($this->getChildren() as $child) {
            $child->delete();
        }

        $result = rmdir($this->path);
        if ($result === false) {
            throw new Sabre\DAV\Exception\ServiceUnavailable('Error while deleting directory');
        }

        Memcache::delete('meta:' . sha1($this->path));

        return true;
    }

    /**
     * @return FilesystemIterator
     */
    public function getDirectoryIterator()
    {
        return new FilesystemIterator(
            $this->path,
            FilesystemIterator::CURRENT_AS_SELF
            | FilesystemIterator::SKIP_DOTS
        );
    }

    /**
     * @return array|INode[]
     * @throws Sabre\DAV\Exception\Forbidden
     * @throws Sabre\DAV\Exception\NotFound
     */
    public function getChildren()
    {
        $nodes = [];
        $iterator = $this->getDirectoryIterator();

        $children_count = iterator_count($iterator);
        if ($children_count > self::MAX_ENTRIES) {
            $this->paginated = true;

            syslog(LOG_INFO, 'Directory contains more than ' . self::MAX_ENTRIES . ' entries!');

            $counter = 0;
            $start = 0;
            if (isset($_GET['page']) && is_numeric($_GET['page']) && $_GET['page'] > 0) {
                $start = (self::MAX_ENTRIES * ($_GET['page'] - 1)) + 1;

                if ($start === 1) {
                    $start = 0;
                }
            }

            $this->pages = round($children_count/self::MAX_ENTRIES);
        }

        if ($this->paginated && !in_array($_SERVER['REQUEST_METHOD'], ['GET', 'POST'])) {
            $nodes[] = new VirtualFile('! TOO MANY FILES, ONLY THE FIRST ' . self::MAX_ENTRIES . ' ENTRIES ARE SHOWN');
            $nodes[] = new VirtualFile('! VISIT WEB INTERFACE FOR PAGINATED LISTING');
        }

        foreach ($iterator as $entry) {
            if ($this->paginated) {
                /** @noinspection PhpUndefinedVariableInspection */
                $counter++;

                /** @noinspection PhpUndefinedVariableInspection */
                if ($counter >= $start) {
                    $nodes[] = $this->getChild($entry->getFilename());
                } else {
                    continue;
                }

                if ($counter >= $start + self::MAX_ENTRIES) {
                    break;
                }
            } else {
                $nodes[] = $this->getChild($entry->getFilename());
            }
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

        if ($name === '.' || $name === '..') {
            throw new Sabre\DAV\Exception\Forbidden('Permission denied to . and ..');
        }

        if (is_dir($path)) {
            return new self($path);
        }

        return new File($path);
    }

    /**
     * @return array
     */
    public function getQuotaInfo()
    {
        return [0, Server::MAX_QUOTA];
    }

    /**
     * @return bool
     */
    public function isPaginated()
    {
        if (is_bool($this->paginated)) {
            return $this->paginated;
        }

        $this->paginated = iterator_count($this->getDirectoryIterator()) > self::MAX_ENTRIES;

        return $this->paginated;
    }

    /**
     * @return int
     */
    public function getPages()
    {
        return $this->isPaginated() ? $this->pages : null;
    }
}
