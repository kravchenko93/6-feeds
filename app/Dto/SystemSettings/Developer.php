<?php

namespace App\Dto\SystemSettings;

class Developer
{
    public ?string $name;

    public ?string $sheetId;

    /**
     * @var string[]
     */
    public array $allowedFeeds;

    /**
     * @var string[]
     */
    public array $warnings;

    public function __construct(
        ?string $name,
        ?string $sheetId,
        array $allowedFeeds,
        array $warnings = []
    ) {
        $this->name = $name;
        $this->sheetId = $sheetId;
        $this->allowedFeeds = $allowedFeeds;
        $this->warnings = $warnings;
    }
}
