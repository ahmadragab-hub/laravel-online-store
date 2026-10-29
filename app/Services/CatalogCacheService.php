<?php

namespace App\Services;

use Illuminate\Support\Facades\Cache;

class CatalogCacheService
{
    private const VERSION_KEY = 'catalog.cache_version';

    private const TTL_SECONDS = 3600;

    public function remember(
        string $scope,
        array $parameters,
        callable $callback,
    ): mixed {
        $key = $this->key($scope, $parameters);

        return Cache::remember($key, self::TTL_SECONDS, $callback);
    }

    public function invalidate(): void
    {
        if (! Cache::has(self::VERSION_KEY)) {
            Cache::forever(self::VERSION_KEY, 1);
        }

        Cache::increment(self::VERSION_KEY);
    }

    private function key(string $scope, array $parameters): string
    {
        ksort($parameters);

        $fingerprint = sha1(json_encode($parameters));

        return "catalog.v{$this->version()}.{$scope}.{$fingerprint}";
    }

    private function version(): int
    {
        return (int) Cache::get(self::VERSION_KEY, 1);
    }
}
