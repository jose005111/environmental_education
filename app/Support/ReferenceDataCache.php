<?php

namespace App\Support;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;

final class ReferenceDataCache
{
    private const TTL_SECONDS = 600;

    public static function remember(string $type, Request $request, Closure $callback): mixed
    {
        $key = sprintf(
            'reference-data:%s:v%d:%s',
            $type,
            self::version($type),
            sha1($request->fullUrl()),
        );

        return Cache::remember($key, self::TTL_SECONDS, $callback);
    }

    public static function invalidate(string $type): void
    {
        Cache::increment(self::versionKey($type));
    }

    private static function version(string $type): int
    {
        return (int) Cache::rememberForever(self::versionKey($type), fn () => 1);
    }

    private static function versionKey(string $type): string
    {
        return "reference-data:{$type}:version";
    }
}
