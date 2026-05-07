<?php

namespace Goldnead\WebhookManager\Auth\Verifiers;

use Goldnead\WebhookManager\Contracts\AuthVerifierInterface;
use Illuminate\Http\Request;

class IpAllowlistVerifier implements AuthVerifierInterface
{
    public function handle(): string
    {
        return 'ip_allowlist';
    }

    public function label(): string
    {
        return 'IP allowlist';
    }

    public function verify(Request $request, array $config): bool
    {
        $allow = (array) ($config['allow'] ?? []);
        if (empty($allow)) {
            return false;
        }
        $ip = (string) $request->ip();

        foreach ($allow as $entry) {
            if (self::matches($ip, $entry)) {
                return true;
            }
        }
        return false;
    }

    public function sign(array $request, array $config): array
    {
        return $request;
    }

    private static function matches(string $ip, string $rule): bool
    {
        if ($ip === $rule) {
            return true;
        }
        if (str_contains($rule, '/')) {
            [$subnet, $mask] = explode('/', $rule);
            $maskBits = (int) $mask;
            return self::cidrMatch($ip, $subnet, $maskBits);
        }
        return false;
    }

    private static function cidrMatch(string $ip, string $subnet, int $mask): bool
    {
        $ipBin = @inet_pton($ip);
        $subnetBin = @inet_pton($subnet);
        if ($ipBin === false || $subnetBin === false || strlen($ipBin) !== strlen($subnetBin)) {
            return false;
        }
        $bytes = intdiv($mask, 8);
        $bits = $mask % 8;
        if ($bytes && substr($ipBin, 0, $bytes) !== substr($subnetBin, 0, $bytes)) {
            return false;
        }
        if ($bits === 0) {
            return true;
        }
        $maskByte = chr(~((1 << (8 - $bits)) - 1) & 0xFF);
        return (ord($ipBin[$bytes]) & ord($maskByte)) === (ord($subnetBin[$bytes]) & ord($maskByte));
    }
}
