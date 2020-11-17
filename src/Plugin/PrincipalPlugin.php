<?php

namespace GAEDrive\Plugin;

use GAEDrive\FS\Collection\RootPrincipalCollection;
use GAEDrive\Plugin\PrincipalBackend\AbstractBackend as PrincipalBackend;
use Sabre;
use Sabre\DAVACL\Plugin as BasePrincipalPlugin;
use Sabre\DAV\INode;
use Sabre\HTTP\RequestInterface;
use Sabre\HTTP\ResponseInterface;

class PrincipalPlugin extends BasePrincipalPlugin
{
    /**
     * @var PrincipalBackend
     */
    protected $principalBackend;

    /**
     * @var string
     */
    protected $principalPrefix;

    /**
     * @param PrincipalBackend $principalBackend
     * @param string           $principalPrefix
     */
    public function __construct(PrincipalBackend $principalBackend, $principalPrefix = 'principals')
    {
        $this->principalBackend           = $principalBackend;
        $this->principalPrefix            = $principalPrefix;
        $this->hideNodesFromListings      = true;
        $this->allowUnauthenticatedAccess = true;
    }

    /**
     * @return array
     */
    public function getPluginInfo()
    {
        return [
            'name'        => $this->getPluginName(),
            'description' => 'Adds support for WebDAV ACL principal system',
            'link'        => 'http://sabre.io/dav/acl/',
        ];
    }

    /**
     * @param string|INode $node
     *
     * @return array
     */
    public function getAcl($node)
    {
        $acl = parent::getAcl($node);
        if ($node instanceof RootPrincipalCollection) {
            // Do not return principals listing in webdav clients (show only in browser)
            return $acl;
        }

        $acl[] = [
            'principal' => $this->principalPrefix . '/groups/administrators',
            'privilege' => '{DAV:}all',
            'protected' => true,
        ];

        return $acl;
    }

    /**
     * @param INode $node
     * @param string    $output
     *
     * @return void
     */
    public function htmlActionsPanel(INode $node, &$output)
    {
        /*if (!$node instanceof PrincipalCollection) {
    return;
    }

    $output .= '<tr><td colspan="2"><form method="post" action="">
    <h3>Create new principal (users only)</h3>
    <input type="hidden" name="sabreAction" value="mkcol" />
    <input type="hidden" name="resourceType" value="{DAV:}principal" />
    <label>Name (uri):</label> <input type="text" name="name" value="' . $this->principalPrefix . '/users/" /><br />
    <label>Display name:</label> <input type="text" name="{DAV:}displayname" /><br />
    <input type="submit" value="create" />
    </form>
    </td></tr>';*/
    }

    /**
     * @param RequestInterface  $request
     * @param ResponseInterface $response
     *
     * @return void
     * @throws Sabre\DAV\Exception
     * @throws Sabre\DAV\Exception\Forbidden
     */
    public function beforeMethod(RequestInterface $request, ResponseInterface $response)
    {
        if (in_array($request->getMethod(), ['LOCK', 'UNLOCK', 'PUT', 'POST', 'PATCH', 'MKCOL', 'MKCALENDAR', 'MOVE', 'COPY', 'DELETE', 'PROPPATCH', 'ACL'])) {
            $currentPrincipal = $this->principalBackend->getCurrentPrincipal();

            if ($this->principalBackend->isReadOnly($currentPrincipal)) {
                throw new Sabre\DAV\Exception\Forbidden('Permission denied to perform write operation');
            }
        }

        parent::beforeMethod($request, $response);
    }
}
