<?php

namespace GAEDrive\Plugin;

use GAEDrive\FS\Collection\HomeCollection;
use GAEDrive\FS\Collection\RootCollection;
use GAEDrive\FS\Collection\RootPrincipalCollection;
use GAEDrive\FS\Directory;
use Sabre\DAVACL\PrincipalCollection as BasePrincipalCollection;
use Sabre\DAV\Browser\Plugin as BaseBrowserPlugin;
use Sabre\DAV\Exception\Conflict;
use Sabre\DAV\Exception\NotFound;
use Sabre\Dav\INode;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;
use Sabre\HTTP\URLUtil;

/**
 * Applies few tweaks and fixes:
 * - adds 'Last-Modified' header to asset replies
 * - hides file/folder creation on home collection node
 * - fixes file upload on GAE
 */
class BrowserPlugin extends BaseBrowserPlugin
{
    /**
     * @var array
     */
    public $uninterestingProperties = [
        '{DAV:}principal-collection-set',
        '{DAV:}acl-restrictions',
        '{DAV:}supportedlock',
        '{DAV:}lockdiscovery',
        '{DAV:}supported-privilege-set',
        '{DAV:}supported-report-set',
        '{DAV:}supported-method-set',
        '{DAV:}resourcetype',
    ];

    /**
     * @return array
     */
    public function getPluginInfo()
    {
        return [
            'name'        => $this->getPluginName(),
            'description' => 'Generates HTML indexes',
            'link'        => 'http://sabre.io/dav/browser-plugin/',
        ];
    }

    /**
     * @param INode  $node
     * @param mixed  $output
     * @param string $path
     */
    public function htmlActionsPanel(INode $node, &$output, $path)
    {
        if ($node instanceof HomeCollection || $node instanceof RootCollection || $node instanceof RootPrincipalCollection || $node instanceof BasePrincipalCollection) {
            return;
        }

        if (method_exists($node, 'isPaginated') && $node->isPaginated()) {
            $output .= '<h3>Big directory detected!</h3>';
            $output .= '<b>Directory contains more than ' . Directory::MAX_ENTRIES . ' entries, only the first page is returned.<br/>You can use the following buttons to display more pages:</b><br/><br/>';

            if (!isset($_GET['page']) || !is_numeric($_GET['page'])) {
                $_GET['page'] = 1;
            }

            if ($_GET['page'] > 1) {
                $previousPageNumber = $_GET['page'] - 1;
                $nextPageNumber     = $_GET['page'] + 1;
            } else {
                $previousPageNumber = null;
                $nextPageNumber     = $_GET['page'] + 1;
            }

            if ($nextPageNumber > $node->getPages()) {
                $nextPageNumber = null;
            }

            if ($previousPageNumber > $node->getPages()) {
                $previousPageNumber = $node->getPages();
            }

            $previousPageNumber && $output .= '<form method="get" action=""><input type="hidden" name="page" value="' . $previousPageNumber . '" /><input type="submit" value="previous page" /></form><br/>';
            $nextPageNumber && $output .= '<form method="get" action=""><input type="hidden" name="page" value="' . $nextPageNumber . '" /><input type="submit" value="next page" /></form><br/>';
        }

        parent::htmlActionsPanel($node, $output, $path);
    }

    /**
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     *
     * @return bool
     * @throws Conflict
     */
    public function httpPOST(RequestInterface $request, ResponseInterface $response)
    {
        $result = parent::httpPOST($request, $response);

        // Fix file upload on GAE
        $postVars = $request->getPostData();
        if ($_FILES && $postVars['sabreAction'] === 'put' && current($_FILES) === false) {
            $file            = reset($_FILES);
            list(, $newName) = URLUtil::splitPath(trim($file['name']));

            if (isset($postVars['name']) && trim($postVars['name'])) {
                $newName = trim($postVars['name']);
            }
            list(, $newName) = URLUtil::splitPath($newName);

            if (is_uploaded_file($file['tmp_name'])) {
                $this->server->createFile($request->getPath() . '/' . $newName, fopen($file['tmp_name'], 'rb'));
            }
        }

        return $result;
    }

    /**
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     *
     * @return bool
     * @throws NotFound
     */
    public function httpGetEarly(RequestInterface $request, ResponseInterface $response)
    {
        $result = parent::httpGetEarly($request, $response);
        if (is_bool($result)) {
            return $result;
        }

        $params = $request->getQueryParameters();
        if (isset($params['sabreAction']) && $params['sabreAction'] === 'asset') {
            $this->serveAsset(isset($params['assetName']) ? $params['assetName'] : null);

            return false;
        }

        return true;
    }

    /**
     * @param string $assetName
     *
     * @throws NotFound
     */
    protected function serveAsset($assetName)
    {
        parent::serveAsset($assetName);
        $assetPath = $this->getLocalAssetPath($assetName);
        $this->server->httpResponse->setHeader('Cache-Control', 'public, max-age=1209600, must-revalidate');
        $this->server->httpResponse->setHeader('Last-Modified', gmdate('D, d M Y H:i:s T', filemtime($assetPath)));
    }

    /**
     * @param string $assetName
     *
     * @return string
     */
    protected function getAssetUrl($assetName)
    {
        return '?sabreAction=asset&assetName=' . urlencode($assetName);
    }
}
