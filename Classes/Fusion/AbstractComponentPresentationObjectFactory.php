<?php

namespace PackageFactory\AtomicFusion\PresentationObjects\Fusion;

/*
 * This file is part of the PackageFactory.AtomicFusion.PresentationObjects package
 */

use Neos\ContentRepository\Domain\NodeType\NodeTypeConstraintFactory;
use Neos\ContentRepository\Domain\Projection\Content\TraversableNodeInterface;
use Neos\ContentRepository\Domain\Projection\Content\TraversableNodes;
use Neos\Eel\ProtectedContextAwareInterface;
use Neos\Flow\Annotations as Flow;
use Neos\Flow\I18n\Translator;
use Neos\Neos\Service\ContentElementEditableService;
use Neos\Neos\Service\ContentElementWrappingService;
use PackageFactory\AtomicFusion\PresentationObjects\Infrastructure\UriService;

/**
 * The generic abstract component presentation object factory implementation
 */
abstract class AbstractComponentPresentationObjectFactory implements ComponentPresentationObjectFactoryInterface, ProtectedContextAwareInterface
{
    /**
     * @Flow\Inject
     * @var UriService
     */
    protected $uriService;

    /**
     * @Flow\Inject
     * @var Translator
     */
    protected $translator;

    /**
     * @Flow\Inject
     * @var ContentElementEditableService
     */
    protected $contentElementEditableService;

    /**
     * @Flow\Inject
     * @var ContentElementWrappingService
     */
    protected $contentElementWrappingService;

    /**
     * @Flow\Inject
     * @var NodeTypeConstraintFactory
     */
    protected $nodeTypeConstraintFactory;

    final protected function createWrapper(TraversableNodeInterface $node, PresentationObjectComponentImplementation $fusionObject): callable
    {
        $wrappingService = $this->contentElementWrappingService;

        return function (string $content) use ($node, $fusionObject, $wrappingService) {
            return $wrappingService->wrapContentObject($node, $content, $fusionObject->getPath());
        };
    }

    final protected function getEditableProperty(TraversableNodeInterface $node, string $propertyName): string
    {
        return $this->contentElementEditableService->wrapContentProperty(
            $node,
            $propertyName,
            $node->getProperty($propertyName) ?: ''
        );
    }

    final protected function findChildNodesByNodeTypeFilterString(TraversableNodeInterface $parentNode, string $nodeTypeFilterString): TraversableNodes
    {
        return $parentNode->findChildNodes($this->nodeTypeConstraintFactory->parseFilterString($nodeTypeFilterString));
    }

    /**
     * All methods are considered safe
     *
     * @param string $methodName
     * @return boolean
     */
    public function allowsCallOfMethod($methodName): bool
    {
        return true;
    }
}
