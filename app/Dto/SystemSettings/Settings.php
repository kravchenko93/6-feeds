<?php

namespace App\Dto\SystemSettings;

class Settings
{
    /**
     * @var Developer[]
     */
    public array $developers;
    /**
     * @var string[]
     */
    public array $rulesSheetIds;

    public function __construct(
        array $developers,
        array $rulesSheetIds
    ) {
        $this->developers = $developers;
        $this->rulesSheetIds = $rulesSheetIds;
    }
}
