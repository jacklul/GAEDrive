<?php

namespace GAEDrive\FS\Collection;

use GAEDrive\FS\HomeDirectory;
use GAEDrive\FS\Traits\ProtectedPropertiesTrait;
use GAEDrive\Plugin\PrincipalBackend\AbstractBackend;
use RuntimeException;
use Sabre;
use Sabre\DAVACL\AbstractPrincipalCollection;
use Sabre\DAVACL\ACLTrait;

class HomeCollection extends AbstractPrincipalCollection implements Sabre\DAVACL\IACL, Sabre\DAV\IProperties
{
    use AclTrait;
    use ProtectedPropertiesTrait;

    /**
     * @var
     */
    protected $path;

    /**
     * @var string
     */
    protected $name;

    /**
     * @var AbstractBackend
     */
    protected $principalBackend;

    /**
     * @var string
     */
    protected $principalPrefixBase;

    /**
     * @param string          $path
     * @param AbstractBackend $principalBackend
     * @param string          $name
     * @param string          $principalPrefix
     */
    public function __construct($path, AbstractBackend $principalBackend, $name = 'home', $principalPrefix = 'principals')
    {
        $this->path = $path;
        $this->name = $name;
        $this->principalPrefixBase = $principalPrefix;

        parent::__construct($principalBackend, $principalPrefix . '/users');
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @return array
     *
     * @throws Sabre\DAV\Exception
     */
    public function getACL()
    {
        if (empty($this->getChildren())) {  // Hides this collection when user does not have home directory
            return [];
        }

        return [
            [
                'privilege' => '{DAV:}read',
                'principal' => '{DAV:}authenticated',
                'protected' => true,
            ],
        ];
    }

    /**
     * @return array
     *
     * @throws Sabre\DAV\Exception
     */
    public function getChildren()
    {
        $currentPrincipal = $this->principalBackend->getCurrentPrincipal();
        if (empty($currentPrincipal)) {
            return [];
        }

        $children = [];
        foreach ($this->principalBackend->getPrincipalsByPrefix($this->principalPrefix) as $principalInfo) {
            if (!$this->principalBackend->isGuest() && ($currentPrincipal === $principalInfo['uri'] || $this->principalBackend->isAdministrator()) && !$this->principalBackend->isGuest($principalInfo['uri'])) {
                $children[] = $this->getChildForPrincipal($principalInfo);
            }
        }

        return $children;
    }

    /**
     * @param array $principalInfo
     *
     * @return HomeDirectory
     */
    public function getChildForPrincipal(array $principalInfo)
    {
        $principalUri = $principalInfo['uri'];
        $principalBaseName = basename($principalInfo['uri']);

        $principalDataPath = $this->path . '/' . $principalBaseName;

        if (!is_dir($principalDataPath) && !mkdir($principalDataPath, 0755, true) && !is_dir($principalDataPath)) {
            throw new RuntimeException(sprintf('Directory "%s" was not created', $principalDataPath));
        }

        return new HomeDirectory($this->path . '/' . $principalBaseName, $principalUri);
    }
}
