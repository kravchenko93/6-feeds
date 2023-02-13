<?php

namespace App\Dto\Xml;

class ElementXml
{
    public const TYPE_STRING = 'type_string';
    public const TYPE_REGEX = 'type_regex';
    public const TYPE_EACH_EXPLODE = 'type_each_explode';
    public const TYPE_CELL_VALUE = 'type_cell_value';
    public const TYPE_CHILDREN = 'type_children';

    public string $name;
    public string $type;
    public ?string $value;
    /**
     * @var ElementXml[]
     */
    public array $children;
    /**
     * @var Attribute[]
     */
    public array $attributions;

    public bool $isEachFlats = false;

    public bool $isEachValue = false;

    public function __construct(
        string $name,
        string $type,
        ?string $value,
        array $children,
        array $attributions
    ) {
        $this->name = $name;
        $this->type = $type;
        $this->value = $value;
        $this->children = $children;
        $this->attributions = $attributions;
    }
}
