<?php
/**
 * MCP Schema Validator
 *
 * Validador minimal de JSON Schema para inputs de tools.
 * Soporta: type, required, properties, additionalProperties, enum,
 *          pattern, minLength, maxLength, minimum, maximum,
 *          minItems, maxItems, items, oneOf.
 */

final class Schema
{
    /**
     * Valida $data contra $schema.
     *
     * @param array $schema  JSON Schema como array PHP.
     * @param mixed $data    Datos a validar.
     * @param string $path   Prefijo de ruta para mensajes (uso interno).
     * @return array         Array de errores ['campo' => 'mensaje']. Vacío si válido.
     */
    public static function validate(array $schema, $data, string $path = ''): array
    {
        $errors = [];

        // oneOf — exactamente una rama debe pasar
        if (isset($schema['oneOf'])) {
            $passing = 0;
            foreach ($schema['oneOf'] as $branch) {
                if (empty(self::validate($branch, $data, $path))) {
                    $passing++;
                }
            }
            if ($passing === 0) {
                $errors[$path ?: 'root'] = 'Debe cumplir exactamente una de las condiciones oneOf';
            }
            // No retornamos aquí: seguimos validando el resto del schema
        }

        // type
        if (isset($schema['type'])) {
            $typeErr = self::checkType($schema['type'], $data, $path);
            if ($typeErr) {
                $errors[$path ?: 'root'] = $typeErr;
                return $errors; // No tiene sentido continuar si el tipo ya falla
            }
        }

        // Validaciones para object
        if (is_array($data) && !self::isIndexed($data)) {
            // required
            if (isset($schema['required'])) {
                foreach ($schema['required'] as $field) {
                    if (!array_key_exists($field, $data)) {
                        $fieldPath = $path ? "{$path}.{$field}" : $field;
                        $errors[$fieldPath] = "El campo '{$field}' es obligatorio";
                    }
                }
            }

            // additionalProperties: false
            if (isset($schema['additionalProperties']) && $schema['additionalProperties'] === false) {
                $allowed = array_keys($schema['properties'] ?? []);
                foreach (array_keys($data) as $key) {
                    if (!in_array($key, $allowed, true)) {
                        $fieldPath = $path ? "{$path}.{$key}" : $key;
                        $errors[$fieldPath] = "Campo no permitido: '{$key}'";
                    }
                }
            }

            // properties
            if (isset($schema['properties'])) {
                foreach ($schema['properties'] as $prop => $propSchema) {
                    if (!array_key_exists($prop, $data)) continue;
                    $fieldPath = $path ? "{$path}.{$prop}" : $prop;
                    $subErrors = self::validate($propSchema, $data[$prop], $fieldPath);
                    $errors    = array_merge($errors, $subErrors);
                }
            }
        }

        // Validaciones para array
        if (is_array($data) && self::isIndexed($data)) {
            if (isset($schema['minItems']) && count($data) < $schema['minItems']) {
                $errors[$path ?: 'root'] = "Mínimo {$schema['minItems']} elementos requeridos";
            }
            if (isset($schema['maxItems']) && count($data) > $schema['maxItems']) {
                $errors[$path ?: 'root'] = "Máximo {$schema['maxItems']} elementos permitidos";
            }
            if (isset($schema['items'])) {
                foreach ($data as $i => $item) {
                    $itemPath  = ($path ? $path : 'root') . "[{$i}]";
                    $subErrors = self::validate($schema['items'], $item, $itemPath);
                    $errors    = array_merge($errors, $subErrors);
                }
            }
        }

        // Validaciones para string
        if (is_string($data)) {
            if (isset($schema['minLength']) && strlen($data) < $schema['minLength']) {
                $errors[$path ?: 'root'] = "Mínimo {$schema['minLength']} caracteres";
            }
            if (isset($schema['maxLength']) && strlen($data) > $schema['maxLength']) {
                $errors[$path ?: 'root'] = "Máximo {$schema['maxLength']} caracteres";
            }
            if (isset($schema['enum']) && !in_array($data, $schema['enum'], true)) {
                $opts = implode(', ', $schema['enum']);
                $errors[$path ?: 'root'] = "Valor inválido. Opciones: {$opts}";
            }
            if (isset($schema['pattern']) && !preg_match($schema['pattern'], $data)) {
                $errors[$path ?: 'root'] = "El valor no cumple el patrón requerido";
            }
        }

        // Validaciones para number/integer
        if (is_numeric($data) && !is_string($data)) {
            if (isset($schema['minimum']) && $data < $schema['minimum']) {
                $errors[$path ?: 'root'] = "El valor mínimo es {$schema['minimum']}";
            }
            if (isset($schema['maximum']) && $data > $schema['maximum']) {
                $errors[$path ?: 'root'] = "El valor máximo es {$schema['maximum']}";
            }
        }

        return $errors;
    }

    // ──────────────────────────────────────────────────────────────────────────

    private static function checkType(string $type, $data, string $path): ?string
    {
        switch ($type) {
            case 'object':
                if (!is_array($data) || self::isIndexed($data)) {
                    return 'Se esperaba un objeto';
                }
                break;
            case 'array':
                if (!is_array($data) || !self::isIndexed($data)) {
                    return 'Se esperaba un array';
                }
                break;
            case 'string':
                if (!is_string($data)) {
                    return 'Se esperaba una cadena de texto';
                }
                break;
            case 'integer':
                if (!is_int($data) && !(is_numeric($data) && floor((float)$data) == (float)$data)) {
                    return 'Se esperaba un entero';
                }
                break;
            case 'number':
                if (!is_numeric($data) || is_string($data)) {
                    return 'Se esperaba un número';
                }
                break;
            case 'boolean':
                if (!is_bool($data)) {
                    return 'Se esperaba un booleano';
                }
                break;
        }
        return null;
    }

    private static function isIndexed(array $arr): bool
    {
        if (empty($arr)) return true;
        return array_keys($arr) === range(0, count($arr) - 1);
    }
}
