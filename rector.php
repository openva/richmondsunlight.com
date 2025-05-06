<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Set\ValueObject\DowngradeSetList;

return static function (RectorConfig $rectorConfig): void {
    // 1) Define which directories Rector should process:
    $rectorConfig->paths([
        __DIR__ . '/htdocs',
        __DIR__ . '/deploy',
    ]);

    // 2) Include the Downgrade sets to stay PHP 5-compatible.
    //    These sets remove or rewrite features from newer PHP versions (7.x, 8.x)
    //    so you don’t break on older environments:
    $rectorConfig->sets([
        DowngradeSetList::PHP_80,
        DowngradeSetList::PHP_74,
        DowngradeSetList::PHP_73,
        DowngradeSetList::PHP_72,
    ]);

    // 3) If you had specific rules previously (like AddVoidReturnTypeWhereNoReturnRector),
    //    remove them or skip them because those rules add modern type hints:
    $rectorConfig->skip([
	__DIR__ . '/htdocs/includes/vendor',
    ]);

    // 4) (Optional) Add additional sets for code quality/cleanup if they don't
    //    introduce modern syntax. For example:
    // $rectorConfig->sets([
    //     SetList::DEAD_CODE,  // But be careful that it doesn't introduce PHP 7+ array destructuring, etc.
    // ]);
};
