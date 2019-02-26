<?php /** @noinspection PhpDeprecationInspection */
/** @noinspection PhpUnusedParameterInspection */
/** @noinspection PhpIncompatibleReturnTypeInspection */
/** @noinspection PhpInconsistentReturnPointsInspection */

namespace GAEDrive\Plugin;

use Sabre\DAV\ICollection;
use Sabre\DAV\Server;
use Sabre\DAV\ServerPlugin;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;
use Sabre\HTTP\URLUtil;

class IgnoreTemporaryFilesPlugin extends ServerPlugin
{
    /**
     * @var Server
     */
    protected $server;

    /**
     * @var array
     */
    public $temporaryFilePatterns = [
        '/^\._(.*)$/',             // OS/X resource forks
        '/^.DS_Store$/',         // OS/X custom folder settings
        '/^desktop.ini$/',         // Windows custom folder settings
        '/^Thumbs.db$/',         // Windows thumbnail cache
        '/^.(.*).swp$/',         // ViM temporary files
        '/^\.dat(.*)$/',         // Smultron seems to create these
        '/^~lock.(.*)#$/',         // Windows 7 lockfiles
        '/^(.*)\.(tmp|temp)$/',  // Generic temporary files
    ];

    /**
     * @param Server $server
     */
    public function initialize(Server $server)
    {
        $this->server = $server;
        $server->on('beforeMethod:*', [$this, 'beforeMethod']);
        $server->on('beforeCreateFile', [$this, 'beforeCreateFile']);
    }

    /**
     * @return array
     */
    public function getPluginInfo()
    {
        return [
            'name'        => $this->getPluginName(),
            'description' => 'Prevents storing files considered as temporary or junk',
            'link'        => 'http://sabre.io/dav/temporary-files/',
        ];
    }

    /**
     * @return string
     */
    public function getPluginName()
    {
        return 'temp-files';
    }

    /**
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     *
     * @return bool
     */
    public function beforeMethod(RequestInterface $request, ResponseInterface $response)
    {
        if (!$tempLocation = $this->isTempFile($request->getPath())) {
            return;
        }

        switch ($request->getMethod()) {
            case 'PUT':
                $response->setStatus(201);

                return false;
            case 'DELETE':
                $response->setStatus(204);

                return false;
        }

        return;
    }

    /**
     * @param string $path
     *
     * @return bool
     */
    protected function isTempFile($path)
    {
        list(, $tempPath) = URLUtil::splitPath($path);

        foreach ($this->temporaryFilePatterns as $tempFile) {
            if (preg_match($tempFile, $tempPath)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param string      $uri
     * @param resource    $data
     * @param ICollection $parent
     * @param bool        $modified
     *
     * @return bool
     */
    public function beforeCreateFile($uri, $data, ICollection $parent, $modified)
    {
        if ($this->isTempFile($uri)) {
            return false;
        }

        return;
    }
}
