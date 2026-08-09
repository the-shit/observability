<?php

declare(strict_types=1);

use TheShit\Observability\Support\CollectorHost;

it('recognises traffic bound for the collector', function () {
    $collector = new CollectorHost('http://odin.lion-balance.ts.net:4318');

    expect($collector->matches('http://odin.lion-balance.ts.net:4318/v1/traces'))->toBeTrue()
        ->and($collector->matches('http://odin.lion-balance.ts.net:4318/v1/logs'))->toBeTrue();
});

it('leaves ordinary outbound traffic alone', function () {
    $collector = new CollectorHost('http://odin.lion-balance.ts.net:4318');

    expect($collector->matches('https://api.github.com/rate_limit'))->toBeFalse();
});

it('matches nothing when the endpoint has no host', function () {
    expect((new CollectorHost(''))->matches('https://api.github.com'))->toBeFalse()
        ->and((new CollectorHost('not a url'))->matches('also not a url'))->toBeFalse();
});
