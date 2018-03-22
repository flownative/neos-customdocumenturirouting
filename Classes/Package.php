<?php
namespace Flownative\Neos\CustomDocumentUriRouting;

/**
 * This file is part of the Flownative.Neos.CustomDocumentUriRouting package.
 *
 * (c) 2017 Karsten Dambekalns, Flownative GmbH
 *
 * This package is Open Source Software. For the full copyright and license
 * information, please view the LICENSE file which was distributed with this
 * source code.
 */

use Neos\ContentRepository\Domain\Model\Node;
use Neos\ContentRepository\Domain\Model\NodeInterface;
use Neos\ContentRepository\Domain\Utility\NodePaths;
use Neos\Eel\FlowQuery\FlowQuery;
use Neos\Flow\Configuration\ConfigurationManager;
use Neos\Flow\Core\Bootstrap;
use Neos\Flow\Package\Package as BasePackage;
use Neos\Neos\Routing\Cache\RouteCacheFlusher;
use Neos\Neos\Routing\FrontendNodeRoutePartHandlerInterface;

/**
 * The Package class, wiring signal/slot during boot.
 */
class Package extends BasePackage
{
    /**
     * @param Bootstrap $bootstrap The current bootstrap
     * @return void
     */
    public function boot(Bootstrap $bootstrap)
    {
        $dispatcher = $bootstrap->getSignalSlotDispatcher();

        $dispatcher->connect(Node::class, 'nodePropertyChanged', function (NodeInterface $node, $propertyName, $oldValue, $newValue) use ($bootstrap) {
            if (!$node->getNodeType()->isOfType('Neos.Neos:Document')) {
                return;
            }

            $uriPathPropertyName = $bootstrap->getObjectManager()->get(ConfigurationManager::class)->getConfiguration(ConfigurationManager::CONFIGURATION_TYPE_SETTINGS, 'Flownative.Neos.CustomDocumentUriRouting.uriPathPropertyName');
            if ($propertyName !== $uriPathPropertyName) {
                return;
            }

            if (!empty($newValue)) {
                $frontendNodeRoutePartHandler = $bootstrap->getObjectManager()->get(FrontendNodeRoutePartHandlerInterface::class);
                $frontendNodeRoutePartHandler->setName('node');

                $possibleUriPath = $initialUriPath = $newValue;
                $i = 1;
                while ($frontendNodeRoutePartHandler->match($possibleUriPath)) {
                    $nodePathAndContext = NodePaths::explodeContextPath($frontendNodeRoutePartHandler->getValue());
                    if ($nodePathAndContext['nodePath'] === $node->getPath()) {
                        break;
                    }
                    $possibleUriPath = $initialUriPath . '-' . $i++;
                }
                $node->setProperty($propertyName, $possibleUriPath);
            }
            $bootstrap->getObjectManager()->get(RouteCacheFlusher::class)->registerNodeChange($node);
        });
    }
}
