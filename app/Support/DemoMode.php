<?php

namespace App\Support;

use RuntimeException;

final class DemoMode
{
    public static function assertSafe(): void
    {
        $connection = config('database.default');
        $database = (string) config("database.connections.{$connection}.database");
        $dedicatedDatabase = str_ends_with(strtolower(basename($database)), '.demo.sqlite');

        if (! config('pickleplay.demo')) {
            if ($dedicatedDatabase) {
                throw new RuntimeException('A demo database requires explicit local demo mode.');
            }

            return;
        }

        $testMemory = app()->environment('testing') && $database === ':memory:';
        if (! app()->environment(['local', 'testing']) || $connection !== 'sqlite'
            || (! $dedicatedDatabase && ! $testMemory)
            || ! in_array(config('mail.default'), ['array', 'log'], true)) {
            throw new RuntimeException('Demo mode requires local/testing, a dedicated .demo.sqlite database, and log/array mail.');
        }
    }

    public static function assertEnabled(): void
    {
        if (! config('pickleplay.demo')) {
            throw new RuntimeException('Enable PICKLEPLAY_DEMO only for an isolated local demonstration.');
        }

        self::assertSafe();
    }
}
