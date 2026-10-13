<?php

declare(strict_types=1);

test('will not use dd')
    ->expect('dd')
    ->not->toBeUsed();

test('will not use dump')
    ->expect('dump')
    ->not->toBeUsed();

test('will not use ray')
    ->expect('ray')
    ->not->toBeUsed();
