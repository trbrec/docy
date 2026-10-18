<?php
declare(strict_types=1);
namespace TrbCrm;

/** Only path metadata: no credentials, network calls, or database writes. */
if (!class_exists(PcloudPathMap::class, false)) {
final class PcloudPathMap
{
    public const LEGACY = '/Candidature';
    public const CANONICAL = '/ChatGPT/Progetti/Websites_CRM_ARTIST_STORE/CRM_TRB/Candidature';

    public static function validate(string $path): string
    {
        if ($path === '' || strlen($path) > 2048 || $path[0] !== '/' || preg_match('/[\x00-\x1f\x7f\\\\]/', $path)) {
            throw new \RuntimeException('Percorso pCloud non valido');
        }
        if ($path !== '/') {
            $body = substr($path, 1); if (str_ends_with($body, '/')) $body = substr($body, 0, -1);
            foreach (explode('/', $body) as $part) {
                if ($part === '' || $part === '.' || $part === '..') throw new \RuntimeException('Percorso pCloud non valido');
            }
        }
        return $path;
    }

    public static function root(string $root): string
    {
        $root = rtrim(self::validate($root), '/');
        if ($root === '') throw new \RuntimeException('Radice pCloud non valida');
        return $root;
    }

    private static function inside(string $path, string $prefix): bool
    {
        return $path === $prefix || str_starts_with($path, $prefix . '/');
    }

    public static function resolve(string $path, string $root = self::LEGACY): string
    {
        self::validate($path); $root = self::root($root);
        foreach ([self::LEGACY, self::CANONICAL] as $prefix) {
            if (self::inside($path, $prefix)) return $root . substr($path, strlen($prefix));
        }
        return $path;
    }

    /** Compare stored legacy and new references without rewriting stored metadata. */
    public static function legacy(string $path, string $root = self::LEGACY): string
    {
        self::validate($path); $root = self::root($root);
        foreach (array_unique([$root, self::CANONICAL]) as $prefix) {
            if (self::inside($path, $prefix)) return self::LEGACY . substr($path, strlen($prefix));
        }
        return $path;
    }

    public static function material(string $path, string $root = self::LEGACY): string
    {
        $legacy = self::legacy($path, $root);
        if (!preg_match('#^/(Candidature|Demo TRB|Upload files - TRB rec)(?:/|$)#D', $legacy)) {
            throw new \RuntimeException('Percorso materiale pCloud non valido');
        }
        return self::resolve($path, $root);
    }

    public static function materialRoot(string $path, string $root = self::LEGACY): string
    {
        $path = self::material($path, $root); $root = self::root($root);
        if (self::inside($path, $root)) return $root;
        if (self::inside($path, self::CANONICAL)) return self::CANONICAL;
        return '/' . explode('/', substr($path, 1))[0];
    }
}
}
