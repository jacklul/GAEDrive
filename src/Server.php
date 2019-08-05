<?php

namespace GAEDrive;

use ErrorException;
use GAEDrive\Helper\Memcache as MemcacheHelper;
use Google\Auth\HttpHandler\Guzzle6HttpHandler;
use Google\Cloud\Datastore\DatastoreClient;
use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Handler\StreamHandler as GuzzleStreamHandler;
use Memcache;
use Monolog\Formatter\LineFormatter;
use Monolog\Handler\SyslogHandler;
use Monolog\Logger;
use Pimple\Container;
use RuntimeException;
use Sabre;
use Sabre\DAV\Server as DAVServer;

/**
 * @property DAVServer       server
 * @property Logger          logger
 * @property DatastoreClient datastore
 * @property Memcache        memcache
 */
class Server
{
    const MAX_QUOTA = 5000000000; // ~5GB = default storage bucket space for always free tier

    /**
     * @var string
     */
    private $path;

    /**
     * @var Container
     */
    private $container;

    /**
     * Constructor
     */
    public function __construct()
    {
        $this->path = getenv('DATA_PATH');
        if (empty($this->path)) {
            $this->path = 'gs://#default#';
        } else {
            while (substr($this->path, -1) === '/') {
                $this->path = substr($this->path, 0, -1);   // Remove ending slash
            }
        }

        set_error_handler(
            static function ($type, $message, $file, $line) {
                switch ($type) {
                    case E_ERROR:
                    case E_PARSE:
                    case E_CORE_ERROR:
                    case E_COMPILE_ERROR:
                    case E_USER_ERROR:
                    case E_RECOVERABLE_ERROR:
                        throw new ErrorException($message, 0, $type, $file, $line);
                }
            }
        );

        $this->configureContainer();
    }

    /**
     * @return void
     */
    private function configureContainer()
    {
        $this->container = new Container();

        /**
         * @return DAVServer
         */
        $this->container['server'] = function () {
            $plugins = [];
            $plugins[] = new Plugin\BrowserPlugin(true);
            $plugins[] = new Plugin\QuotaCheckPlugin();
            $plugins[] = new Plugin\IgnoreTemporaryFilesPlugin();

            $authBackend = new Plugin\AuthBackend\DatastoreBasic($this->datastore, $this->memcache);
            $plugins[] = $authPlugin = new Plugin\AuthPlugin($authBackend);

            $principalBackend = new Plugin\PrincipalBackend\AuthBackend($authPlugin);
            $plugins[] = new Plugin\PrincipalPlugin($principalBackend);

            $locksBackend = new Plugin\LocksBackend\Memcache($this->memcache);
            $plugins[] = new Plugin\LocksPlugin($locksBackend);

            $propertyStorageBackend = new Plugin\PropertyStorageBackend\Memcache($this->memcache, $this->path);
            $plugins[] = new Plugin\PropertyStoragePlugin($propertyStorageBackend);

            $rootCollection = [
                new FS\Collection\RootPrincipalCollection($principalBackend),
                new FS\Collection\HomeCollection($this->path . '/private', $principalBackend),
                new FS\Collection\SharedColletion($this->path . '/shared', $principalBackend),
                new FS\Collection\PublicColletion($this->path . '/public'),
            ];

            $server = new Sabre\DAV\Server(new FS\Collection\RootCollection($rootCollection, $authPlugin, false));
            Sabre\DAV\Server::$exposeVersion = false;
            $server->setLogger($this->logger);
            $server->setBaseUri('/');

            foreach ($plugins as $plugin) {
                $server->addPlugin($plugin);
            }
            unset($plugins);

            return $server;
        };


        /**
         * @return Logger
         */
        $this->container['logger'] = static function () {
            $logger = new Logger('GAEDrive');

            $handler = new SyslogHandler('syslog');
            $handler->setFormatter(new LineFormatter("%message% %context% %extra%\n"));
            $logger->pushHandler($handler);

            return $logger;
        };

        /**
         * @return DatastoreClient
         */
        $this->container['datastore'] = static function () {
            $options = [];
            if (isset($_SERVER['DEFAULT_VERSION_HOSTNAME']) && empty(getenv('GOOGLE_CLOUD_PROJECT'))) {
                $options['projectId'] = explode('.', $_SERVER['DEFAULT_VERSION_HOSTNAME'])[0];
            }

            // On GAE we don't have fully functional cURL so it's best to use stream handler
            $options['httpHandler'] = new Guzzle6HttpHandler(
                new GuzzleClient(
                    [
                        'handler' => new GuzzleStreamHandler(),
                        'verify'  => false,
                    ]
                )
            );
            $options['authHttpHandler'] = $options['httpHandler'];

            return new DatastoreClient($options);
        };

        /**
         * @return Memcache
         */
        $this->container['memcache'] = static function () {
            $memcache = new Memcache();
            MemcacheHelper::init($memcache);

            return $memcache;
        };
    }

    /**
     * @return void
     */
    public function run()
    {
        if (!$this->server instanceof DAVServer) {
            throw new RuntimeException('Server is not initialized');
        }

        $this->server->start();
    }


    /**
     * @noinspection MagicMethodsValidityInspection
     *
     * @param string $key
     *
     * @return mixed|null
     */
    public function __get($key)
    {
        if ($this->container->offsetExists($key)) {
            return $this->container->offsetGet($key);
        }

        return null;
    }
}
