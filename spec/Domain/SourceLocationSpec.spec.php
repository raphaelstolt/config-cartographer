<?php

declare(strict_types=1);

use ConfigCartographer\Domain\SourceLocation;

describe(SourceLocation::class, function () {
    it('stores file and line', function () {
        $loc = new SourceLocation('.env', 5);
        expect($loc->file)->toBe('.env');
        expect($loc->line)->toBe(5);
        expect($loc->approximate)->toBeFalse();
    });

    it('stores approximate flag', function () {
        $loc = new SourceLocation('config/packages/doctrine.yaml', 12, true);
        expect($loc->approximate)->toBeTrue();
    });

    it('formats as string with line', function () {
        $loc = new SourceLocation('.env', 5);
        expect((string) $loc)->toBe('.env:5');
    });

    it('formats as string with approximate line', function () {
        $loc = new SourceLocation('config/packages/doctrine.yaml', 12, true);
        expect((string) $loc)->toBe('config/packages/doctrine.yaml:12~');
    });

    it('formats as string without line', function () {
        $loc = new SourceLocation('.env', null);
        expect((string) $loc)->toBe('.env');
    });

    it('provides equality', function () {
        $loc = new SourceLocation('.env', 5);
        $other = new SourceLocation('.env', 5);
        $different = new SourceLocation('.env', 6);

        expect($loc->equals($other))->toBeTrue();
        expect($loc->equals($different))->toBeFalse();
    });
});
