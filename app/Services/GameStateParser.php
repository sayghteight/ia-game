<?php

namespace App\Services;

/**
 * Parsea el bloque [STATE] que el GameMaster devuelve al final
 * de cada turno para mantener persistido el estado del personaje
 * y del mundo.
 */
class GameStateParser
{
    public const PATTERN = '/\[STATE\](.*?)\[\/STATE\]/s';

    /**
     * Extrae y devuelve un array con los campos del bloque [STATE].
     * Si no hay bloque, devuelve null.
     *
     * @return array{hp: ?int, hp_max: ?int, xp: ?int, gold: ?int, location: ?string, inventory: ?array, notes: ?string}|null
     */
    public function extract(string $content): ?array
    {
        if (! preg_match(self::PATTERN, $content, $matches)) {
            return null;
        }

        $block = $matches[1];
        $values = [
            'hp' => null,
            'hp_max' => null,
            'xp' => null,
            'gold' => null,
            'location' => null,
            'inventory' => null,
            'notes' => null,
        ];

        $lines = preg_split('/\r?\n/', $block) ?: [];
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line === '' || ! str_contains($line, ':')) {
                continue;
            }
            [$key, $value] = array_map('trim', explode(':', $line, 2));
            $key = strtolower($key);

            switch ($key) {
                case 'hp':
                    if (str_contains($value, '/')) {
                        [$h, $hm] = array_map('trim', explode('/', $value, 2));
                        $values['hp'] = is_numeric($h) ? (int) $h : null;
                        $values['hp_max'] = is_numeric($hm) ? (int) $hm : null;
                    } else {
                        $values['hp'] = is_numeric($value) ? (int) $value : null;
                    }
                    break;
                case 'xp':
                    $values['xp'] = is_numeric($value) ? (int) $value : null;
                    break;
                case 'gold':
                    $values['gold'] = is_numeric($value) ? (int) $value : null;
                    break;
                case 'location':
                    $values['location'] = $value;
                    break;
                case 'inventory':
                    $items = array_filter(array_map('trim', explode(',', $value)));
                    $values['inventory'] = array_values($items);
                    break;
                case 'notes':
                    $values['notes'] = $value;
                    break;
            }
        }

        return $values;
    }

    /**
     * Devuelve el contenido SIN el bloque [STATE] (para mostrarlo al jugador).
     */
    public function stripState(string $content): string
    {
        return trim(preg_replace(self::PATTERN, '', $content) ?? $content);
    }
}
