<?php /** @noinspection PhpUnusedParameterInspection */

namespace GAEDrive\Plugin;

use GAEDrive\Helper\Memcache;
use GAEDrive\Server;
use Sabre;
use Sabre\DAV\INode;
use Sabre\DAV\Node;
use Sabre\DAV\Server as DAVServer;
use Sabre\DAV\ServerPlugin;

class QuotaCheckPlugin extends ServerPlugin
{
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
    }

    /**
     * @return array
     */
    public function getPluginInfo()
    {
        return [
            'name'        => $this->getPluginName(),
            'description' => 'Denies creation and writing to files when they exceed the quota',
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

        if ($length) {
            $freeSpace = $this->getFreeSpace($path);

            if ($length > 32000000) {
                throw new Sabre\DAV\Exception\InsufficientStorage('Max file size exceeded');
            }

            if ($length > $freeSpace) {
                throw new Sabre\DAV\Exception\InsufficientStorage('Insufficient storage space');
            }
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
     * @param string $path
     *
     * @return int
     */
    public function getFreeSpace($path = null)
    {
        $quota = Memcache::get('quota');
        if (is_array($quota)) {
            return $quota[1];
        }

        return Server::MAX_QUOTA;
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
}
