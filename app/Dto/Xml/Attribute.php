<?php

namespace App\Dto\Xml;

class Attribute
{
    public const TYPE_STRING = 'type_string';
    public const TYPE_CELL_VALUE = 'type_cell_value';

    public string $type;
    public string $name;
    public string $value;

    public function __construct(
        string $type,
        string $name,
        string $value
    ) {
        $this->type = $type;
        $this->name = $name;
        $this->value = $value;
    }
}
