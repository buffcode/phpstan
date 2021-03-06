<?php declare(strict_types = 1);

namespace PHPStan\Type;

class UnionIterableType implements IterableType, UnionType
{

	use IterableTypeTrait;

	/** @var \PHPStan\Type\Type[] */
	private $types;

	public function __construct(
		Type $itemType,
		array $types
	)
	{
		$this->itemType = $itemType;

		$throwException = function () use ($itemType, $types) {
			throw new \PHPStan\ShouldNotHappenException(sprintf(
				'Cannot create %s with: %s, %s',
				self::class,
				$itemType->describe(),
				implode(', ', array_map(function (Type $type): string {
					return $type->describe();
				}, $types))
			));
		};
		if (count($types) < 1) {
			$throwException();
		}
		foreach ($types as $type) {
			if ($type instanceof IterableType || $type instanceof UnionType) {
				$throwException();
			}
		}
		$this->types = UnionTypeHelper::sortTypes($types);
	}

	/**
	 * @return string|null
	 */
	public function getClass()
	{
		return UnionTypeHelper::getClass($this->getTypes());
	}

	/**
	 * @return string[]
	 */
	public function getReferencedClasses(): array
	{
		$classes = UnionTypeHelper::getReferencedClasses($this->getTypes());
		$classes = array_merge($classes, $this->getItemType()->getReferencedClasses());

		return $classes;
	}

	/**
	 * @return \PHPStan\Type\Type[]
	 */
	public function getTypes(): array
	{
		return $this->types;
	}

	public function combineWith(Type $otherType): Type
	{
		return TypeCombinator::combine($this, $otherType);
	}

	public function accepts(Type $type): bool
	{
		if ($type instanceof MixedType) {
			return true;
		}

		$accepts = UnionTypeHelper::accepts($this, $type);
		if ($accepts !== null && !$accepts) {
			return false;
		}

		if ($type instanceof IterableType) {
			return $this->getItemType()->accepts($type->getItemType());
		}

		if (TypeCombinator::shouldSkipUnionTypeAccepts($this)) {
			return true;
		}

		foreach ($this->getTypes() as $otherType) {
			if ($otherType->accepts($type)) {
				return true;
			}
		}

		return false;
	}

	public function describe(): string
	{
		return sprintf('%s[]|%s', $this->getItemType()->describe(), UnionTypeHelper::describe($this->getTypes()));
	}

	public function canAccessProperties(): bool
	{
		return UnionTypeHelper::canAccessProperties($this->getTypes());
	}

	public function canCallMethods(): bool
	{
		return UnionTypeHelper::canCallMethods($this->getTypes());
	}

	public function isDocumentableNatively(): bool
	{
		return false;
	}

	public function resolveStatic(string $className): Type
	{
		$itemType = $this->getItemType();
		if ($itemType instanceof StaticResolvableType) {
			$itemType = $itemType->resolveStatic($className);
		}

		return new self(
			$itemType,
			UnionTypeHelper::resolveStatic($className, $this->getTypes())
		);
	}

	public function changeBaseClass(string $className): StaticResolvableType
	{
		$itemType = $this->getItemType();
		if ($itemType instanceof StaticResolvableType) {
			$itemType = $itemType->changeBaseClass($className);
		}

		return new self(
			$itemType,
			UnionTypeHelper::changeBaseClass($className, $this->getTypes())
		);
	}

}
