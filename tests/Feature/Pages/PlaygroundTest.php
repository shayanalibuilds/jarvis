<?php

declare(strict_types=1);

use function Pest\Laravel\get;

test('playground page requires authentication', function (): void {
    get('/some-team/playground')->assertRedirect(route('login'));
});
