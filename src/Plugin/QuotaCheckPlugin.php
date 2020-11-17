<?php

namespace GAEDrive\Plugin;

use Sabre;
use Sabre\DAV\INode;
use Sabre\DAV\IQuota;
use Sabre\DAV\Node;
use Sabre\DAV\PropFind;
use Sabre\DAV\Server as DAVServer;
use Sabre\DAV\ServerPlugin;

class QuotaCheckPlugin extends ServerPlugin
{
    const MAX_QUOTA     = 5368706371; // ~5 GB
    const MAX_FILE_SIZE = 33554432; // 32 MB

    /**
     * @var DAVServer
     */
    protected $server;

    /**
     * @param DAVServer $server
     *
     * @return void
     */
    public function initialize(DAVServer $server)
    {
        $this->server = $server;

        $server->on('beforeWriteContent', [$this, 'handleBeforeWriteContent'], 10);
        $server->on('beforeCreateFile', [$this, 'handleBeforeCreateFile'], 10);
        $server->on('propFind', [$this, 'propFind'], 100);
    }

    /**
     * @return array
     */
    public function getPluginInfo()
    {
        return [
            'name'        => $this->getPluginName(),
            'description' => 'Denies creation and writing to files when they exceed file size limit',
            'link'        => 'https://github.com/owncloud/core/blob/master/apps/dav/lib/Connector/Sabre/QuotaPlugin.php',
        ];
    }

    /**
     * @return string
     */
    public function getPluginName()
    {
        return 'quota-check';
    }

    /**
     * @param string   $uri
     * @param INode    $node
     * @param resource $data
     * @param bool     $modified
     *
     * @return bool
     * @throws Sabre\DAV\Exception\InsufficientStorage
     */
    public function handleBeforeWriteContent($uri, INode $node, $data, $modified)
    {
        if (!$node instanceof Node) {
            return true;
        }

        return $this->checkQuota($uri);
    }

    /**
     * @param string $path
     * @param null   $length
     *
     * @return bool
     * @throws Sabre\DAV\Exception\InsufficientStorage
     */
    public function checkQuota($path, $length = null)
    {
        if ($length === null) {
            $length = $this->getLength();
        }

        if ($length && $length > self::MAX_FILE_SIZE) {
            throw new Sabre\DAV\Exception\InsufficientStorage('Max file size exceeded (over ' . self::MAX_FILE_SIZE . ' bytes)');
        }

        return true;
    }

    /**
     * @return int|null
     */
    public function getLength()
    {
        $req = $this->server->httpRequest;

        $length = $req->getHeader('X-Expected-Entity-Length');
        if (!is_numeric($length)) {
            $length = $req->getHeader('Content-Length');
            $length = is_numeric($length) ? $length : null;
        }

        $ocLength = $req->getHeader('OC-Total-Length');
        if (is_numeric($length) && is_numeric($ocLength)) {
            return max($length, $ocLength);
        }

        return $length;
    }

    /**
     * @param string   $uri
     * @param resource $data
     * @param INode    $parent
     * @param bool     $modified
     *
     * @return bool
     * @throws Sabre\DAV\Exception\InsufficientStorage
     */
    public function handleBeforeCreateFile($uri, $data, INode $parent, $modified)
    {
        if (!$parent instanceof Node) {
            return true;
        }

        return $this->checkQuota($uri);
    }

    /**
     * @param PropFind $propFind
     * @param INode    $node
     *
     * @return void
     */
    public function propFind(PropFind $propFind, INode $node)
    {
        if ($node instanceof IQuota) {
            $propFind->set('{DAV:}quota-available-bytes', null);
            $propFind->set('{DAV:}quota-used-bytes', null);
            $propFind->set('{DAV:}storage-max-quota', self::MAX_QUOTA);
            $propFind->set('{DAV:}file-max-size', self::MAX_FILE_SIZE);
        }
    }
}
