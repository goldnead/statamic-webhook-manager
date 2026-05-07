<?php

namespace Goldnead\WebhookManager\Support;

/**
 * Best-effort snapshot of Statamic source objects into associative arrays.
 *
 * Each method is defensive: it accepts any input, prefers known accessor
 * methods, and falls back to property access. This keeps unit tests
 * decoupled from a full Statamic boot while still giving real data when
 * Statamic objects are passed at runtime.
 */
final class StatamicSnapshot
{
    public static function entry(mixed $entry): array
    {
        if (is_array($entry)) {
            return $entry;
        }

        $data = method_exists($entry, 'data') ? self::callOrEmpty($entry, 'data') : [];

        return self::compactNonNull([
            'id' => self::callIfExists($entry, 'id'),
            'slug' => self::callIfExists($entry, 'slug'),
            'uri' => self::callIfExists($entry, 'uri'),
            'url' => self::callIfExists($entry, 'url'),
            'title' => self::callIfExists($entry, 'title') ?? ($data['title'] ?? null),
            'collection' => self::callOnRelated($entry, 'collection', 'handle'),
            'site' => self::callOnRelated($entry, 'site', 'handle'),
            'locale' => self::callIfExists($entry, 'locale'),
            'published' => self::callIfExists($entry, 'published'),
            'status' => self::callIfExists($entry, 'status'),
            'data' => is_array($data) ? $data : (method_exists($data, 'all') ? $data->all() : []),
            'updated_at' => self::dateString(self::callIfExists($entry, 'lastModified')),
        ]);
    }

    public static function formSubmission(mixed $submission): array
    {
        if (is_array($submission)) {
            return $submission;
        }

        $data = method_exists($submission, 'data') ? self::callOrEmpty($submission, 'data') : [];

        return self::compactNonNull([
            'id' => self::callIfExists($submission, 'id'),
            'form' => self::callOnRelated($submission, 'form', 'handle'),
            'site' => self::callOnRelated($submission, 'site', 'handle'),
            'data' => is_array($data) ? $data : (method_exists($data, 'all') ? $data->all() : []),
            'created_at' => self::dateString(self::callIfExists($submission, 'date')),
        ]);
    }

    public static function user(mixed $user): array
    {
        if (is_array($user)) {
            return $user;
        }

        return self::compactNonNull([
            'id' => self::callIfExists($user, 'id'),
            'email' => self::callIfExists($user, 'email'),
            'name' => self::callIfExists($user, 'name'),
            'roles' => self::callRolesHandles($user),
        ]);
    }

    public static function asset(mixed $asset): array
    {
        if (is_array($asset)) {
            return $asset;
        }

        return self::compactNonNull([
            'id' => self::callIfExists($asset, 'id'),
            'path' => self::callIfExists($asset, 'path'),
            'url' => self::callIfExists($asset, 'url'),
            'extension' => self::callIfExists($asset, 'extension'),
            'size' => self::callIfExists($asset, 'size'),
            'container' => self::callOnRelated($asset, 'container', 'handle'),
        ]);
    }

    private static function compactNonNull(array $arr): array
    {
        return array_filter($arr, fn ($v) => $v !== null);
    }

    private static function callIfExists(mixed $obj, string $method): mixed
    {
        if (! is_object($obj)) {
            return null;
        }
        if (! method_exists($obj, $method)) {
            return $obj->{$method} ?? null;
        }
        try {
            $value = $obj->{$method}();
            return is_scalar($value) || is_null($value) ? $value : (string) $value;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function callOrEmpty(mixed $obj, string $method): mixed
    {
        try {
            return $obj->{$method}();
        } catch (\Throwable $e) {
            return [];
        }
    }

    private static function callOnRelated(mixed $obj, string $accessor, string $sub): ?string
    {
        if (! is_object($obj) || ! method_exists($obj, $accessor)) {
            return null;
        }
        try {
            $related = $obj->{$accessor}();
            if (! $related) {
                return null;
            }
            if (is_object($related) && method_exists($related, $sub)) {
                return (string) $related->{$sub}();
            }
            return null;
        } catch (\Throwable $e) {
            return null;
        }
    }

    private static function callRolesHandles(mixed $user): ?array
    {
        if (! is_object($user) || ! method_exists($user, 'roles')) {
            return null;
        }
        try {
            $roles = $user->roles();
            if (is_object($roles) && method_exists($roles, 'pluck')) {
                return $roles->pluck('handle')->all();
            }
        } catch (\Throwable $e) {
            // ignore
        }
        return null;
    }

    private static function dateString(mixed $value): ?string
    {
        if (! $value) {
            return null;
        }
        if (is_string($value)) {
            return $value;
        }
        if ($value instanceof \DateTimeInterface) {
            return $value->format(\DateTimeInterface::ATOM);
        }
        if (is_object($value) && method_exists($value, 'toIso8601String')) {
            try {
                return $value->toIso8601String();
            } catch (\Throwable $e) {
                return null;
            }
        }
        return null;
    }
}
