<?php

declare(strict_types=1);

namespace App\Services;

class TemplateRenderer
{
    private const PATTERN = '/\{\{\s*([a-zA-Z_][a-zA-Z0-9_]*)\s*\}\}/';

    /**
     * @return list<string>
     */
    public function extractVariables(string $body): array
    {
        preg_match_all(self::PATTERN, $body, $matches);

        return array_values(array_unique($matches[1]));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function render(string $body, array $data): string
    {
        return preg_replace_callback(
            self::PATTERN,
            fn (array $match) => array_key_exists($match[1], $data) ? (string) $data[$match[1]] : $match[0],
            $body,
        );
    }

    /**
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    public function missingVariables(string $body, array $data): array
    {
        return array_values(array_diff($this->extractVariables($body), array_keys($data)));
    }
}
