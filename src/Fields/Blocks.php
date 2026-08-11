<?php

declare(strict_types=1);

namespace Inertify\Form\Fields;

use Inertify\Form\Support\Rules\BlockSetLimits;
use InvalidArgumentException;

class Blocks extends Field
{
    /** @var list<BlockSet> */
    protected array $blockSets = [];

    protected ?int $minimumItems = null;

    protected ?int $maximumItems = null;

    /** @param array<BlockSet> $blocks */
    public function blocks(array $blocks): static
    {
        $types = array_map(fn (BlockSet $set): string => $set->getName(), $blocks);

        if (count($types) !== count(array_unique($types))) {
            throw new InvalidArgumentException('Block set types must be unique within a Blocks field.');
        }

        $this->blockSets = array_values($blocks);
        $this->managedRule('array', 'array');

        return $this;
    }

    /** @param array<BlockSet> $sets */
    public function sets(array $sets): static
    {
        return $this->blocks($sets);
    }

    public function set(BlockSet $set): static
    {
        return $this->blocks([...$this->blockSets, $set]);
    }

    /** @param array<BlockSet> $blocks */
    public function schema(array $blocks): static
    {
        return $this->blocks($blocks);
    }

    /** @return list<BlockSet> */
    public function getBlockSets(): array
    {
        return $this->blockSets;
    }

    public function minItems(?int $minimum): static
    {
        if ($minimum !== null && ($minimum < 0 || ($this->maximumItems !== null && $minimum > $this->maximumItems))) {
            throw new InvalidArgumentException('Blocks minimum must be non-negative and not exceed its maximum.');
        }

        $this->minimumItems = $minimum;
        $this->managedRule('minItems', $minimum === null ? null : 'min:'.$minimum);

        return $this->option('minItems', $minimum);
    }

    public function maxItems(?int $maximum): static
    {
        if ($maximum !== null && ($maximum < 0 || ($this->minimumItems !== null && $maximum < $this->minimumItems))) {
            throw new InvalidArgumentException('Blocks maximum must be non-negative and not be less than its minimum.');
        }

        $this->maximumItems = $maximum;
        $this->managedRule('maxItems', $maximum === null ? null : 'max:'.$maximum);

        return $this->option('maxItems', $maximum);
    }

    public function minBlocks(?int $minimum): static
    {
        return $this->minItems($minimum);
    }

    public function maxBlocks(?int $maximum): static
    {
        return $this->maxItems($maximum);
    }

    public function reorderable(bool $reorderable = true): static
    {
        return $this->option('reorderable', $reorderable);
    }

    /** @param array<string, mixed> $data */
    protected function serializedOptions(array $data): array
    {
        return [
            ...parent::serializedOptions($data),
            'sets' => array_values(array_map(
                fn (BlockSet $block): array => $block->toArrayFor($data),
                array_filter($this->blockSets, fn (BlockSet $block): bool => $block->isAuthorized()),
            )),
        ];
    }

    public function emptyValue(): mixed
    {
        return [];
    }

    /** @return list<mixed> */
    public function getRules(array $data = [], ?array $row = null): array
    {
        $rules = parent::getRules($data, $row);

        if ($rules === ['exclude']) {
            return $rules;
        }

        $limits = [];
        foreach ($this->blockSets as $set) {
            if ($set->isAuthorized() && $set->getMaxItems() !== null) {
                $limits[$set->getName()] = $set->getMaxItems();
            }
        }

        return [
            ...$rules,
            'array',
            ...($limits === [] ? [] : [new BlockSetLimits($limits)]),
        ];
    }
}
