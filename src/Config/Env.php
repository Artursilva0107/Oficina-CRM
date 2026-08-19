<?php

declare(strict_types=1);

namespace App\Config;

/**
 * Carregador simples de variáveis de ambiente a partir do arquivo .env.
 * Evita dependência obrigatória de biblioteca externa caso o Composer
 * ainda não tenha sido instalado no ambiente de destino.
 */
final class Env
{
    private static bool $loaded = false;

    public static function load(string $basePath): void
    {
        if (self::$loaded) {
            return;
        }

        $path = rtrim($basePath, '/') . '/.env';

        if (is_file($path)) {
            $lines = file($path, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($lines as $line) {
                $line = trim($line);
                if ($line === '' || str_starts_with($line, '#')) {
                    continue;
                }
                if (!str_contains($line, '=')) {
                    continue;
                }
                [$name, $value] = explode('=', $line, 2);
                $name = trim($name);
                $value = trim($value);
                $value = trim($value, "\"'");

                if ($name !== '' && getenv($name) === false) {
                    putenv("{$name}={$value}");
                    $_ENV[$name] = $value;
                }
            }
        }

        self::$loaded = true;
    }

    public static function get(string $key, ?string $default = null): ?string
    {
        $value = getenv($key);
        if ($value === false) {
            return $default;
        }
        return $value;
    }

    public static function required(string $key): string
    {
        $value = self::get($key);
        if ($value === null || $value === '') {
            throw new \RuntimeException("Variável de ambiente obrigatória ausente: {$key}");
        }
        return $value;
    }
}
