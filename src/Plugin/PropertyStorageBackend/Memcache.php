<?php

namespace GAEDrive\Plugin\PropertyStorageBackend;

use Memcache as NativeMemcache;
use Sabre\DAV\PropertyStorage\Backend\BackendInterface;
use Sabre\DAV\PropFind;
use Sabre\DAV\PropPatch;
use Sabre\DAV\Xml\Property\Complex;

/**
 * Stores properties in Memcache keys
 */
class Memcache implements BackendInterface
{
    /**
     * @var NativeMemcache
     */
    protected $memcache;

    /**
     * @var string|null
     */
    protected $root_path;

    /**
     * @param NativeMemcache $memcache
     * @param string|null    $root_path
     */
    public function __construct(NativeMemcache $memcache, $root_path = null)
    {
        $this->memcache = $memcache;
        $this->root_path = $root_path;
    }

    /**
     * @param string   $path
     * @param PropFind $propFind
     */
    public function propFind($path, PropFind $propFind)
    {

        if (!$propFind->isAllProps() && count($propFind->get404Properties()) === 0) {
            return;
        }

        $props = $this->memcache->get('props:' . sha1($path));
        if ($props !== false) {
            foreach ($props as $prop) {
                switch ($prop['type']) {
                    case null:
                    case 1:
                        $propFind->set($prop['name'], $prop['value']);
                        break;
                    case 2:
                        $propFind->set($prop['name'], new Complex($prop['value']));
                        break;
                    case 3:
                        $propFind->set($prop['name'], unserialize($prop['value']));
                        break;
                }
            }
        }
    }

    /**
     * @param string    $path
     * @param PropPatch $propPatch
     */
    public function propPatch($path, PropPatch $propPatch)
    {
        $propPatch->handleRemaining(function ($properties) use ($path) {
            $properties_new = [];
            foreach ($properties as $name => $value) {
                if ($value !== null) {
                    if (is_scalar($value)) {
                        $valueType = 1;
                    } elseif ($value instanceof Complex) {
                        $valueType = 2;
                        $value = $value->getXml();
                    } else {
                        $valueType = 3;
                        $value = serialize($value);
                    }

                    $properties_new[] = [
                        'type'  => $valueType,
                        'name'  => $name,
                        'value' => $value,
                    ];
                }
            }

            return $this->memcache->set('props:' . sha1($path), $properties_new);
        });
    }

    /**
     * @param string $path
     */
    public function delete($path)
    {
        $this->memcache->delete('props:' . sha1($path));
    }

    /**
     * @param string $source
     * @param string $destination
     */
    public function move($source, $destination)
    {
        $props = $this->memcache->get('props:' . sha1($source));
        if ($props !== false) {
            $this->memcache->delete('props:' . sha1($source));
            $this->memcache->set('props:' . sha1($destination), $props);
        }

        if ($this->root_path !== null) {
            // Attempt at updating child props...
            if (is_dir($this->root_path . '/' . $destination . '/')) {
                $iterator = new \FilesystemIterator(
                    $this->root_path . '/' . $destination . '/',
                    \FilesystemIterator::CURRENT_AS_SELF
                    | \FilesystemIterator::SKIP_DOTS
                );

                foreach ($iterator as $node) {
                    $newLocation = str_replace($this->root_path . '/', '', $node->getPathname());
                    $oldLocation = str_replace($destination, $source, $newLocation);

                    $props = $this->memcache->get('props:' . sha1($oldLocation));
                    if ($props !== false) {
                        $this->memcache->delete('props:' . sha1($oldLocation));
                        $this->memcache->set('props:' . sha1($newLocation), $props);
                    }
                }
            } elseif (is_dir($this->root_path . '/' . $source . '/')) {
                $iterator = new \FilesystemIterator(
                    $this->root_path . '/' . $source . '/',
                    \FilesystemIterator::CURRENT_AS_SELF
                    | \FilesystemIterator::SKIP_DOTS
                );

                foreach ($iterator as $node) {
                    $oldLocation = str_replace($this->root_path . '/', '', $node->getPathname());
                    $newLocation = str_replace($source, $destination, $oldLocation);

                    $props = $this->memcache->get('props:' . sha1($oldLocation));
                    if ($props !== false) {
                        $this->memcache->delete('props:' . sha1($oldLocation));
                        $this->memcache->set('props:' . sha1($newLocation), $props);
                    }
                }
            }
        }
    }
}
