<?php

declare(strict_types=1);

use TheShit\Observability\Support\ServiceVersionResolver;

function makeRepo(string $head, array $files = []): string
{
    $base = sys_get_temp_dir().'/obs-'.bin2hex(random_bytes(6));
    mkdir($base.'/.git', 0777, true);
    file_put_contents($base.'/.git/HEAD', $head);

    foreach ($files as $relative => $contents) {
        $path = $base.'/.git/'.$relative;

        if (! is_dir(dirname($path))) {
            mkdir(dirname($path), 0777, true);
        }

        file_put_contents($path, $contents);
    }

    return $base;
}

it('prefers an explicitly configured version', function () {
    $resolver = new ServiceVersionResolver(makeRepo("ref: refs/heads/main\n"), 'v2.1.0');

    expect($resolver->resolve())->toBe('v2.1.0');
});

it('reads the short sha from a loose ref', function () {
    $base = makeRepo("ref: refs/heads/main\n", [
        'refs/heads/main' => "1234567890abcdef1234567890abcdef12345678\n",
    ]);

    expect((new ServiceVersionResolver($base))->resolve())->toBe('1234567890ab');
});

it('reads the short sha from packed-refs when the ref is not loose', function () {
    $base = makeRepo("ref: refs/heads/main\n", [
        'packed-refs' => "# pack-refs with: peeled fully-peeled sorted\nfedcba9876543210fedcba9876543210fedcba98 refs/heads/main\n",
    ]);

    expect((new ServiceVersionResolver($base))->resolve())->toBe('fedcba987654');
});

it('reads a detached HEAD directly', function () {
    $base = makeRepo("0123456789abcdef0123456789abcdef01234567\n");

    expect((new ServiceVersionResolver($base))->resolve())->toBe('0123456789ab');
});

it('falls back to unknown outside a git checkout', function () {
    expect((new ServiceVersionResolver(sys_get_temp_dir().'/definitely-not-a-repo'))->resolve())->toBe('unknown');
});

it('falls back to unknown when the configured version is empty', function () {
    expect((new ServiceVersionResolver(sys_get_temp_dir().'/nope', ''))->resolve())->toBe('unknown');
});

it('resolves once per instance', function () {
    $base = makeRepo("ref: refs/heads/main\n", [
        'refs/heads/main' => "1111111111111111111111111111111111111111\n",
    ]);

    $resolver = new ServiceVersionResolver($base);

    expect($resolver->resolve())->toBe('111111111111');

    file_put_contents($base.'/.git/refs/heads/main', "2222222222222222222222222222222222222222\n");

    expect($resolver->resolve())->toBe('111111111111');
});
