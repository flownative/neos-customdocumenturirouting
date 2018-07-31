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

use Doctrine\DBAL\FetchMode;
use Doctrine\ORM\EntityManagerInterface;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Model\Workspace;
use Neos\ContentRepository\Domain\Repository\NodeDataRepository;
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
     * @Flow\InjectConfiguration(path="mixinNodeTypeName")
     * @var string
     */
    protected $mixinNodeTypeName;

    /**
     * @Flow\InjectConfiguration(path="uriPathPropertyName")
     * @var string
     */
    protected $uriPathPropertyName;

    /**
     * @Flow\InjectConfiguration(path="matchExcludePatterns")
     * @var array
     */
    protected $matchExcludePatterns;

    /**
     * @Flow\Inject
     * @var NodeDataRepository
     */
    protected $nodeDataRepository;

    /**
     * @Flow\Inject
     * @var EntityManagerInterface
     */
    protected $entityManager;

    /**
     * Builds a node path which matches the given request path.
     *
     * This method looks for nodes with the configured uriPathPropertyName property having a matching value.
     *
     * If no node is found that way, it asks the parent method to resolve the node path as usual.
     * ☝️ Note: due to the hot fix (see below), the parent method is currently not called
     *
     * @param NodeInterface $siteNode The site node, used as a starting point while traversing the tree
     * @param string $relativeRequestPath The request path, relative to the site's root path
     * @return string
     * @throws \Neos\Eel\Exception
     * @throws \Doctrine\DBAL\DBALException
     * @throws Exception\NoSuchNodeException
     */
    protected function getRelativeNodePathByUriPathSegmentProperties(NodeInterface $siteNode, $relativeRequestPath)
    {
        $q = new FlowQuery([$siteNode]);
        $foundNode = $q->find(
            sprintf(
                '[instanceof %s][%s="%s"]',
                $this->mixinNodeTypeName,
                $this->uriPathPropertyName,
                $relativeRequestPath)
        )->get(0);

        if ($foundNode instanceof NodeInterface) {
            return NodePaths::getRelativePathBetween($siteNode->getPath(), $foundNode->getPath());
        }

        // Hot fix for performance issue in original Neos route part handler: if a given node contains thousands of
        // child nodes, the original implementation would load all these nodes into memory in order to compare the
        // current path segment with $node->getProperty('uriPathSegment').
        //
        // When the issue is fixed in Neos core, the following code can be removed by a parent::… call.

        // The DBAL implementation does not support multiple dimensions yet, therefore fall back to original implementation:
        $dimensionPresets = $this->contentDimensionPresetSource->getAllPresets();
        if (count($dimensionPresets) > 1) {
            return parent::getRelativeNodePathByUriPathSegmentProperties($siteNode, $relativeRequestPath);
        }
        if (count($dimensionPresets) === 1) {
            $firstPreset = reset($dimensionPresets);
            if (count($firstPreset['presets']) > 1) {
                return parent::getRelativeNodePathByUriPathSegmentProperties($siteNode, $relativeRequestPath);
            }
        }

        $relativeNodePathSegments = [];
        $currentNodeRecord = [
            'path' => $siteNode->getPath()
        ];

        $connection = $this->entityManager->getConnection();

        $queryTemplate = "
              SELECT path, parentPath
              FROM neos_contentrepository_domain_model_nodedata AS nodedata
              WHERE LOWER(CAST(nodedata.properties AS CHAR)) LIKE '%s'
              AND nodedata.parentpathhash = '%s'
              AND workspace IN (%s)
              ORDER BY FIELD(workspace, %s) ASC
              LIMIT 1
        ";
        $workspaceNames = [$siteNode->getContext()->getWorkspace()->getName()];
        $baseWorkspaces = $siteNode->getContext()->getWorkspace()->getBaseWorkspaces();
        array_walk($baseWorkspaces, function (Workspace $workspace) use (&$workspaceNames) {
            $workspaceNames[] = $workspace->getName();
        });

        foreach (explode('/', $relativeRequestPath) as $pathSegment) {
            $statement = $connection->query(sprintf(
                $queryTemplate,
                "%\"uripathsegment\": \"$pathSegment\"%",
                md5($currentNodeRecord['path']),
                "'" . implode("','", $workspaceNames) . "'",
                "'" . implode("','", $workspaceNames) . "'"
            ));

            $fetchResult = $statement->fetch(FetchMode::ASSOCIATIVE);
            if ($fetchResult === false) {
                return false;
            }

            $currentNodeRecord = $fetchResult;
            $relativeNodePathSegments[] = substr($currentNodeRecord['path'], (strrpos($currentNodeRecord['path'], '/') + 1));
        }

        return implode('/', $relativeNodePathSegments);
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

        // Check for exclude patterns and skip further matching
        foreach ($this->matchExcludePatterns as $exclude) {
            if (strpos($requestPath, $exclude) === 0) {
                throw new Exception\NoSuchNodeException(sprintf('Request paths starting with "%s" are excluded for path matching - path: %s', $exclude, $requestPath), 1515959518);
            }
        }

        return parent::convertRequestPathToNode($requestPath);
    }
}
