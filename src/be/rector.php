<?php

declare(strict_types=1);

use Rector\Config\RectorConfig;
use Rector\Php74\Rector\Property\TypedPropertyRector;
use Rector\Php80\Rector\FunctionLike\UnionTypesRector;
use Rector\Php81\Rector\Property\ReadOnlyPropertyRector;
use Rector\Php82\Rector\Class_\ReadOnlyClassRector;
use Rector\CodeQuality\Rector\Class_\InlineConstructorDefaultToPropertyRector;
use Rector\CodeQuality\Rector\If_\SimplifyIfReturnBoolRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPrivateMethodRector;
use Rector\DeadCode\Rector\ClassMethod\RemoveUnusedPublicMethodRector;
use Rector\DeadCode\Rector\Property\RemoveUnusedPrivatePropertyRector;
use Rector\Laravel\Set\LaravelSetList;
use Rector\Set\ValueObject\LevelSetList;

return static function (RectorConfig $rectorConfig): void {
    // Paths to refactor
    $rectorConfig->paths([
        __DIR__.'/App',
        __DIR__.'/tests',
        __DIR__.'/database',
    ]);

    // Register rules
    $rectorConfig->rule(InlineConstructorDefaultToPropertyRector::class);
    $rectorConfig->rule(TypedPropertyRector::class);
    $rectorConfig->rule(ReadOnlyPropertyRector::class);
    $rectorConfig->rule(SimplifyIfReturnBoolRector::class);
    $rectorConfig->rule(RemoveUnusedPrivateMethodRector::class);
    $rectorConfig->rule(RemoveUnusedPrivatePropertyRector::class);

    // Sets - define rule sets
    $rectorConfig->sets([
        LevelSetList::UP_TO_PHP_83,
        LaravelSetList::LARAVEL_110,
    ]);

    // Skip certain rules
    $rectorConfig->skip([
        ReadOnlyClassRector::class,
        RemoveUnusedPublicMethodRector::class,
        UnionTypesRector::class, // Skip for now to avoid breaking changes
    ]);

    // Skip certain files/directories
    $rectorConfig->skip([
        __DIR__.'/vendor',
        __DIR__.'/storage',
        __DIR__.'/bootstrap/cache',
    ]);

    // Parallel processing
    $rectorConfig->parallel();

    // Import names
    $rectorConfig->importNames();

    // Import short classes
    $rectorConfig->importShortClasses();
};
