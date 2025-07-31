<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\LevelSetList;
use Rector\Set\ValueObject\SetList;

return static function (RectorConfig $rectorConfig): void {
    $rectorConfig->paths([
        __DIR__ . '/src',
        __DIR__ . '/tests',
    ]);

    // Define what rule sets will be applied
    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_83,
        SetList::CODE_QUALITY,
        SetList::DEAD_CODE,
        SetList::EARLY_RETURN,
        SetList::TYPE_DECLARATION,
        SetList::PRIVATIZATION,
    ]);

    // Skip some rules or files
    $rectorConfig->skip([
        // Skip specific rules
        // \Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPromotedPropertyRector::class,
    ]);

    // Auto-import fully qualified class names
    $rectorConfig->importNames();
    $rectorConfig->importShortClasses();
};
