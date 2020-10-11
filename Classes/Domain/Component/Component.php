<?php
namespace PackageFactory\AtomicFusion\PresentationObjects\Domain\Component;

/*
 * This file is part of the PackageFactory.AtomicFusion.PresentationObjects package
 */

use Neos\Flow\Annotations as Flow;
use PackageFactory\AtomicFusion\PresentationObjects\Domain\Component\PropType;
use PackageFactory\AtomicFusion\PresentationObjects\Domain\Component\PropTypeIsInvalid;
use PackageFactory\AtomicFusion\PresentationObjects\Domain\Component\PropTypeRepository;

/**
 * @Flow\Proxy(false)
 */
final class Component
{
    /**
     * @var string
     */
    private $packageKey;

    /**
     * @var string
     */
    private $name;

    /**
     * @var array|PropType[];
     */
    private $props;

    public function __construct(string $packageKey, string $name, array $props, ?PropTypeRepository $propTypeRepository = null)
    {
        foreach ($props as &$propType) {
            if (is_string($propType)) {
                $propType = $propTypeRepository->findByType($propType);
            }
        }
        $this->packageKey = $packageKey;
        $this->name = $name;
        $this->props = $props;
    }

    public static function fromInput(string $packageKey, string $name, array $serializedProps, PropTypeRepository $propTypeRepository): self
    {
        $props = [];
        foreach ($serializedProps as $serializedProp) {
            list($propName, $serializedPropType) = explode(':', $serializedProp);
            $propType = $propTypeRepository->findByType($serializedPropType);
            if (is_null($propType)) {
                throw PropTypeIsInvalid::becauseItIsNoKnownComponentOrPrimitive($serializedPropType);
            }
            $props[$propName] = $propType;
        }

        return new self(
            $packageKey,
            $name,
            $props
        );
    }

    public function getPackageKey(): string
    {
        return $this->packageKey;
    }

    public function getName(): string
    {
        return $this->name;
    }

    /**
     * @return array|PropType[]
     */
    public function getProps()
    {
        return $this->props;
    }

    public function isLeaf(): bool
    {
        foreach ($this->props as $propType) {
            if (!$propType->isPrimitive()) {
                return false;
            }
        }

        return true;
    }

    public function getFactoryName(): string
    {
        return $this->getNamespace() . '\\' . $this->name . 'Factory';
    }

    public function getHelperName(): string
    {
        return \mb_substr($this->getPackageKey(), \mb_strrpos($this->getPackageKey(), '.') + 1) . '.' . $this->getName();
    }

    public function getInterfacePath(string $packagePath): string
    {
        return $packagePath . 'Classes/Presentation/' . $this->name . '/' . $this->name . 'Interface.php';
    }

    public function getClassPath(string $packagePath): string
    {
        return $packagePath . 'Classes/Presentation/' . $this->name . '/' . $this->name . '.php';
    }

    public function getFactoryPath(string $packagePath): string
    {
        return $packagePath . 'Classes/Presentation/' . $this->name . '/' . $this->name . 'Factory.php';
    }

    public function getFusionPath(string $packagePath): string
    {
        return $packagePath . 'Resources/Private/Fusion/Presentation/' . ($this->isLeaf() ? 'Leaf' : 'Composite') . '/' . $this->name . '/' . $this->name . '.fusion';
    }

    public function getInterfaceContent(): string
    {
        return '<?php
namespace ' . $this->getNamespace() . ';

/*
 * This file is part of the ' . $this->getPackageKey() . ' package.
 */

interface ' . $this->getName() . 'Interface
{
    ' . trim (implode("\n\n    ", $this->getAccessors(true))) .  '
}
';
    }

    public function getClassContent(): string
    {
        return '<?php
namespace ' . $this->getNamespace() . ';

/*
 * This file is part of the ' . $this->getPackageKey() . ' package.
 */

use Neos\Flow\Annotations as Flow;
use PackageFactory\AtomicFusion\PresentationObjects\Fusion\AbstractComponentPresentationObject;

/**
 * @Flow\Proxy(false)
 */
final class ' . $this->getName() . ' extends AbstractComponentPresentationObject implements ' . $this->getName() . 'Interface
{
    ' . trim (implode("\n\n    ", $this->getProperties())) .  '

    ' . $this->renderConstructor() .  '

    ' . trim (implode("\n\n    ", $this->getAccessors(false))) .  '
}
';
    }

    public function getFactoryContent(): string
    {
        return '<?php
namespace ' . $this->getNamespace() . ';

/*
 * This file is part of the ' . $this->getPackageKey() . ' package.
 */

use PackageFactory\AtomicFusion\PresentationObjects\Fusion\AbstractComponentPresentationObjectFactory;

final class ' . $this->getName() . 'Factory extends AbstractComponentPresentationObjectFactory
{
}
';
    }

    public function getFusionContent(): string
    {
        $terms = [];
        foreach ($this->props as $propName => $propType) {
            $terms[] = '        <dt>' . $propName . ':</dt>
        <dd>{presentationObject.' . $propName . '}</dd>';
        }

        return 'prototype(' . $this->packageKey . ':Component.' . $this->name . ') < prototype(PackageFactory.AtomicFusion.PresentationObjects:PresentationObjectComponent) {
    @presentationObjectInterface = \'' . $this->getNamespace() .  '\\' . $this->name . 'Interface\'

    renderer = afx`<dl>
        ' . trim(implode("\n", $terms)) . '
    </dl>`
}
';
    }

    private function getNamespace(): string
    {
        return \str_replace('.', '\\', $this->packageKey) . '\Presentation\\' . $this->name;
    }

    private function getProperties(): array
    {
        $properties = [];
        foreach ($this->props as $propName => $propType) {
            $properties[] = '/**
     * @var ' . $propType->toVar() . '
     */
    private $' . $propName . ';';
        }

        return $properties;
    }

    private function renderConstructor(): string
    {
        $arguments = [];
        $setters = [];
        foreach ($this->props as $propName => $propType) {
            $arguments[] = $propType->toType() . ' $' . $propName . ',';
            $setters[] = '$this->' . $propName . ' = $' . $propName . ';';
        }
        return 'public function __construct(
        ' . trim(trim(implode("\n        ", $arguments)), ',') .  '
    ) {
        ' . trim(implode("\n        ", $setters)) .  '
    }';
    }

    private function getAccessors(bool $abstract = false): array
    {
        $accessors = [];
        foreach ($this->props as $propName => $propType) {
            $accessorHeader =  'public function get' . ucfirst($propName) . '(): ' . $propType->toType();

            $accessors[] = $accessorHeader . ($abstract ? ';' : '
    {
        return $this->' . $propName . ';
    }') ;
        }

        return $accessors;
    }
}
