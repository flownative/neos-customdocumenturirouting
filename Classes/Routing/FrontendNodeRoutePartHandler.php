<?php
namespace Flownative\Neos\CustomDocumentUriRouting\Routing;

/**
 * This file is part of the Flownative.Neos.CustomDocumentUriRouting package.
 *
 * (c) 2017 Karsten Dambekalns, Flownative GmbH
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Utility\NodePaths;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Annotations as Flow;
use Neos\Neos\Domain\Service\SiteService;
use Neos\Neos\Routing\Exception as Exception;
use Neos\Neos\Routing\FrontendNodeRoutePartHandler as NeosFrontendNodeRoutePartHandler;

/**
 * FrontendNodeRoutePartHandler supporting custom document paths.
 */
class FrontendNodeRoutePartHandler extends NeosFrontendNodeRoutePartHandler
{
    /**
     * A "hack" to read the value from the expected place, not this package.
     * @Flow\InjectConfiguration(package="Neos.Neos", path="routing.supportEmptySegmentForDimensions")
     *
     * @var boolean
     */
    protected $supportEmptySegmentForDimensions;

    /**
     * @Flow\InjectConfiguration(path="uriPathPropertyName")
     * @var string
     */
    protected $uriPathPropertyName;

    /**
     * Builds a node path which matches the given request path.
     *
     * This method loos for nodes with the configured uriPathPropertyName property having a matching value.
     *
     * If no node is found that way, it asks the parent method to resolve the node path as usual.
     *
     * @param NodeInterface $siteNode The site node, used as a starting point while traversing the tree
     * @param string $relativeRequestPath The request path, relative to the site's root path
     * @throws \Neos\Neos\Routing\Exception\NoSuchNodeException
     * @return string
     */
    protected function getRelativeNodePathByUriPathSegmentProperties(NodeInterface $siteNode, $relativeRequestPath)
    {
        $q = new FlowQuery([$siteNode]);
        $foundNode = $q->find(
            sprintf(
                '[instanceof Neos.Neos:Document][%s="%s"]',
                $this->uriPathPropertyName,
                $relativeRequestPath)
        )->get(0);

        if ($foundNode instanceof NodeInterface) {
            return NodePaths::getRelativePathBetween($siteNode->getPath(), $foundNode->getPath());
        }

        return parent::getRelativeNodePathByUriPathSegmentProperties($siteNode, $relativeRequestPath);
    }

    /**
     * Resolves the request path, also known as route path, identifying the given node.
     *
     * A path is built, based on the configured uriPath property. If that is not set, the path is built as
     * usual, based on the uri path segment properties of the parents of and the given node itself.
     *
     * If content dimensions are configured, the first path segment will the identifiers of the dimension
     * values according to the current context.
     *
     * @param NodeInterface $node The node where the generated path should lead to
     * @return string The relative route path, possibly prefixed with a segment for identifying the current content dimension values
     * @throws \Exception
     * @throws \Neos\Neos\Routing\Exception\MissingNodePropertyException
     */
    protected function resolveRoutePathForNode(NodeInterface $node)
    {
        $workspaceName = $node->getContext()->getWorkspaceName();

        $nodeContextPath = $node->getContextPath();
        $nodeContextPathSuffix = ($workspaceName !== 'live') ? substr($nodeContextPath, strpos($nodeContextPath, '@')) : '';

        $currentNodeIsSiteNode = ($node->getParentPath() === SiteService::SITES_ROOT_PATH);
        $dimensionsUriSegment = $this->getUriSegmentForDimensions($node->getContext()->getDimensions(), $currentNodeIsSiteNode);

        $requestPath = $node->getProperty($this->uriPathPropertyName);
        if (empty($requestPath)) {
            $requestPath = $this->getRequestPathByNode($node);
        }

        return trim($dimensionsUriSegment . $requestPath, '/') . $nodeContextPathSuffix;
    }

    /**
     * Returns the initialized node that is referenced by $requestPath, based on the node's
     * "uriPathSegment" property.
     *
     * Note that $requestPath will be modified (passed by reference) by buildContextFromRequestPath().
     *
     * @param string $requestPath The request path, for example /the/node/path@some-workspace
     * @return NodeInterface
     * @throws \Neos\Neos\Routing\Exception\NoWorkspaceException
     * @throws \Neos\Neos\Routing\Exception\NoSiteException
     * @throws \Neos\Neos\Routing\Exception\NoSuchNodeException
     * @throws \Neos\Neos\Routing\Exception\NoSiteNodeException
     * @throws \Neos\Neos\Routing\Exception\InvalidRequestPathException
     */
    protected function convertRequestPathToNode($requestPath)
    {
        // Custom check if request path is a backend request and skip further matching.
        if ($requestPath === 'neos' || strpos($requestPath, 'neos/') === 0) {
            throw new Exception\NoSuchNodeException(sprintf('No match possible because "%s" is a backend request path', $requestPath), 1512649661);
        }

        return parent::convertRequestPathToNode($requestPath);
    }
}
