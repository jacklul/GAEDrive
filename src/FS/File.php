<?php

namespace GAEDrive\FS;

use GAEDrive\Helper\Memcache;
use Sabre;

class File extends Node implements Sabre\DAV\IFile
{
    /**
     * @return bool|mixed|resource
     * @throws Sabre\DAV\Exception\ServiceUnavailable
     */
    public function get()
    {
        $contents = fopen($this->path, 'rb');
        if ($contents === false) {
            throw new Sabre\DAV\Exception\ServiceUnavailable('Error while reading file');
        }

        return $contents;
    }

    /**
     * @param resource|string $data
     *
     * @return null|string
     * @throws Sabre\DAV\Exception\ServiceUnavailable
     */
    public function put($data)
    {
        $result = file_put_contents($this->path, $data);
        if ($result === false) {
            throw new Sabre\DAV\Exception\ServiceUnavailable('Error while writing data');
        }

        clearstatcache(true, $this->path);
        Memcache::delete('meta:' . sha1($this->path));
        Memcache::set('quota_rescan', time());

        return $this->getETag();
    }

    /**
     * @return string|null
     */
    public function getETag()
    {
        return '"' . sha1($this->getSize() . $this->getLastModified()) . '"';
    }

    /**
     * @return int
     */
    public function getSize()
    {
        $cached_meta = Memcache::get('meta:' . sha1($this->path));
        if (!is_array($cached_meta)) {
            $cached_meta = [];
        }

        if (!isset($cached_meta['filesize'])) {
            $cached_meta['filesize'] = filesize($this->path);
        }

        if ($cached_meta['filesize'] > 0) {
            Memcache::set('meta:' . sha1($this->path), $cached_meta, 3600);
        }

        return $cached_meta['filesize'];
    }

    /**
     * @return bool
     * @throws Sabre\DAV\Exception\ServiceUnavailable
     */
    public function delete()
    {
        $result = unlink($this->path);
        if ($result === false) {
            throw new Sabre\DAV\Exception\ServiceUnavailable('Error while deleting file');
        }

        Memcache::delete('meta:' . sha1($this->path));
        Memcache::set('quota_rescan', time());

        return true;
    }

    /**
     * @return string|null
     */
    public function getContentType()
    {
        $cached_meta = Memcache::get('meta:' . sha1($this->path));
        if (!is_array($cached_meta)) {
            $cached_meta = [];
        }

        if (!isset($cached_meta['content_type'])) {
            if (function_exists('mime_content_type')) {
                $cached_meta['content_type'] = mime_content_type($this->path);
            } else {
                $cached_meta['content_type'] = null;
            }
        }

        if ($cached_meta['content_type'] !== null) {
            Memcache::set('meta:' . sha1($this->path), $cached_meta, 3600);
        }

        return $cached_meta['content_type'];
    }
}
