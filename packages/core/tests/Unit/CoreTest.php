<?php

use Hitzordu\Core\Core;

it('reports its version', function () {
    expect(Core::version())->toBe('0.1.0');
});
