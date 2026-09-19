<?php

declare(strict_types=1);

namespace App\Core;

/**
 * Server-side validation.
 *
 * The browser may validate for speed, but every rule here runs again on the
 * server: a request that skips the UI (curl, a script, a tampered form) still
 * cannot insert invalid data.
 *
 * Supported rules: required, nullable, sometimes, string, int, integer, numeric,
 * bool, boolean, email, url, slug, date, datetime, array, json, min:N, max:N,
 * between:A,B, in:a,b,c, not_in:a,b,c, regex:/…/, confirmed, same:field,
 * different:field, unique:table,column[,ignoreId], exists:table,column,
 * image, password, phone, timezone, color.
 */
final class Validator
{
    /** @var array<string,array<int,string>> */
    private array $errors = [];

    /** @var array<string,mixed> */
    private array $validated = [];

    private bool $ran = false;

    /** @param array<string,array<int,string>|string> $rules */
    public function __construct(private array $data, private array $rules, private array $attributes = [])
    {
    }

    /** @param array<string,array<int,string>|string> $rules */
    public static function make(array $data, array $rules, array $attributes = []): self
    {
        return new self($data, $rules, $attributes);
    }

    public function fails(): bool
    {
        $this->run();

        return $this->errors !== [];
    }

    public function passes(): bool
    {
        return !$this->fails();
    }

    /** @return array<string,mixed> */
    public function validate(): array
    {
        if ($this->fails()) {
            throw new ValidationException('Please review the highlighted fields.', $this->errors);
        }

        return $this->validated;
    }

    /** @return array<string,array<int,string>> */
    public function errors(): array
    {
        $this->run();

        return $this->errors;
    }

    /** @return array<string,mixed> */
    public function validated(): array
    {
        $this->run();

        return $this->validated;
    }

    private function run(): void
    {
        if ($this->ran) {
            return;
        }

        $this->ran = true;

        foreach ($this->rules as $field => $rules) {
            $rules = is_array($rules) ? self::normaliseRules($rules) : explode('|', (string) $rules);
            $value = $this->value($field);
            $isPresent = $this->present($field);

            if (!$isPresent && in_array('sometimes', $rules, true)) {
                continue;
            }

            if (in_array('nullable', $rules, true) && ($value === null || $value === '')) {
                $this->validated[$field] = null;
                continue;
            }

            $hasFreePass = in_array('required', $rules, true) ? false : (!$isPresent || $value === null || $value === '');

            foreach ($rules as $rule) {
                if ($rule === '' || in_array($rule, ['nullable', 'sometimes'], true)) {
                    continue;
                }

                [$name, $parameter] = array_pad(explode(':', $rule, 2), 2, null);

                if ($hasFreePass && $name !== 'required') {
                    continue;
                }

                $message = $this->check($name, $field, $value, $parameter);

                if ($message !== null) {
                    $this->errors[$field][] = $this->message($field, $message, $parameter);
                    break;
                }
            }

            if (!isset($this->errors[$field])) {
                $this->validated[$field] = $this->castForStorage($field, $value, $rules);
            }
        }
    }

    private function check(string $rule, string $field, mixed $value, ?string $parameter): ?string
    {
        switch ($rule) {
            case 'required':
                if ($value === null || $value === '' || (is_array($value) && $value === []) || $value === false) {
                    return 'The :label field is required.';
                }
                return null;

            case 'string':
                return is_string($value) || is_numeric($value) ? null : 'The :label must be text.';

            case 'int':
            case 'integer':
                return filter_var(Str::toAsciiDigits((string) $value), FILTER_VALIDATE_INT) !== false ? null : 'The :label must be a whole number.';

            case 'numeric':
                return is_numeric(Str::toAsciiDigits((string) $value)) ? null : 'The :label must be a number.';

            case 'bool':
            case 'boolean':
                return in_array($value, [true, false, 0, 1, '0', '1', 'on', 'off', 'true', 'false'], true) ? null : 'The :label must be true or false.';

            case 'email':
                return filter_var((string) $value, FILTER_VALIDATE_EMAIL) !== false ? null : 'Enter a valid email address.';

            case 'url':
                return filter_var((string) $value, FILTER_VALIDATE_URL) !== false ? null : 'Enter a valid URL (https://…).';

            case 'slug':
                return preg_match('/^[\p{L}\p{N}]+(?:-[\p{L}\p{N}]+)*$/u', (string) $value) === 1
                    ? null : 'The :label may contain letters, numbers and dashes only.';

            case 'date':
                return strtotime((string) $value) !== false ? null : 'The :label must be a valid date.';

            case 'datetime':
                return strtotime((string) $value) !== false ? null : 'The :label must be a valid date and time.';

            case 'timezone':
                return in_array((string) $value, timezone_identifiers_list(), true) ? null : 'The :label must be a valid timezone.';

            case 'color':
                return preg_match('/^#(?:[0-9a-f]{3}|[0-9a-f]{6})$/i', (string) $value) === 1 ? null : 'Use a hex colour such as #34d399.';

            case 'array':
                return is_array($value) ? null : 'The :label must be a list.';

            case 'json':
                if (is_array($value)) {
                    return null;
                }
                Json::decode((string) $value, null);

                return Json::lastError() === 'none' ? null : 'The :label must be valid JSON.';

            case 'min':
                $min = (float) $parameter;

                if (is_numeric($value) && !is_string($value)) {
                    return (float) $value >= $min ? null : 'The :label must be at least ' . $parameter . '.';
                }

                return mb_strlen((string) $value, 'UTF-8') >= $min ? null : 'The :label must be at least ' . $parameter . ' characters.';

            case 'max':
                $max = (float) $parameter;

                if (is_numeric($value) && !is_string($value)) {
                    return (float) $value <= $max ? null : 'The :label may not be greater than ' . $parameter . '.';
                }

                return mb_strlen((string) $value, 'UTF-8') <= $max ? null : 'The :label may not exceed ' . $parameter . ' characters.';

            case 'between':
                [$low, $high] = array_pad(explode(',', (string) $parameter), 2, '0');
                $length = is_numeric($value) && !is_string($value) ? (float) $value : mb_strlen((string) $value, 'UTF-8');

                return $length >= (float) $low && $length <= (float) $high
                    ? null : 'The :label must be between ' . $low . ' and ' . $high . '.';

            case 'in':
                return in_array((string) $value, explode(',', (string) $parameter), true) ? null : 'The selected :label is invalid.';

            case 'not_in':
                return in_array((string) $value, explode(',', (string) $parameter), true) ? 'The selected :label is not allowed.' : null;

            case 'regex':
                return preg_match('/' . str_replace('/', '\/', (string) $parameter) . '/u', (string) $value) === 1
                    ? null : 'The :label format is invalid.';

            case 'confirmed':
                return (string) $value === (string) $this->value($field . '_confirmation') ? null : 'The :label confirmation does not match.';

            case 'same':
                return (string) $value === (string) $this->value((string) $parameter) ? null : 'The :label must match the ' . $parameter . ' field.';

            case 'different':
                return (string) $value !== (string) $this->value((string) $parameter) ? null : 'The :label must be different from the ' . $parameter . ' field.';

            case 'unique':
                [$table, $column, $ignore] = array_pad(explode(',', (string) $parameter), 3, null);

                return $this->uniqueCheck((string) $table, (string) ($column ?? $field), $value, $ignore);

            case 'exists':
                [$table, $column] = array_pad(explode(',', (string) $parameter), 2, null);

                return Database::table((string) $table)->where((string) ($column ?? 'id'), $value)->exists()
                    ? null : 'The selected :label no longer exists.';

            case 'image':
                return preg_match('/\.(jpe?g|png|webp|gif|avif|svg)$/i', (string) $value) === 1 ? null : 'The :label must be an image.';

            case 'password':
                $issues = Security::passwordIssues((string) $value);

                if ($issues === []) {
                    return null;
                }

                $config = (array) Config::get('security.password', []);

                return 'The :label must be at least ' . (int) ($config['min_length'] ?? 10) . ' characters and mix upper case, lower case and numbers.';

            case 'phone':
                return preg_match('/^[0-9+\-\s()]{6,25}$/', Str::toAsciiDigits((string) $value)) === 1 ? null : 'Enter a valid phone number.';

            default:
                return null;
        }
    }

    private function uniqueCheck(string $table, string $column, mixed $value, ?string $ignore): ?string
    {
        $query = Database::table($table)->where($column, $value);

        if ($ignore !== null && $ignore !== '' && $ignore !== 'null') {
            $query->where('id', '!=', (int) $ignore);
        }

        return $query->count() === 0 ? null : 'This :label is already in use.';
    }

    private function value(string $field): mixed
    {
        if (array_key_exists($field, $this->data)) {
            return $this->data[$field];
        }

        $segments = explode('.', $field);
        $value = $this->data;

        foreach ($segments as $segment) {
            if (is_array($value) && array_key_exists($segment, $value)) {
                $value = $value[$segment];
                continue;
            }

            return null;
        }

        return $value;
    }

    /**
     * Accept both spellings of a rule set:
     *   ['required', 'max:190']            → classic strings
     *   ['required', 'max' => 190, 'in' => ['a','b']]  → PHP-friendly arrays
     * The second form is what the admin resource registry uses.
     *
     * @param array<int|string,mixed> $rules
     * @return array<int,string>
     */
    private static function normaliseRules(array $rules): array
    {
        $out = [];

        foreach ($rules as $key => $rule) {
            if (is_string($key)) {
                if ($rule === true) {
                    $out[] = $key;
                    continue;
                }

                $parameter = is_array($rule) ? implode(',', array_map('strval', $rule)) : (string) $rule;
                $out[] = $key . ':' . $parameter;
                continue;
            }

            if (is_string($rule) && $rule !== '') {
                $out[] = $rule;
            }
        }

        return $out;
    }

    private function present(string $field): bool
    {
        return $this->value($field) !== null;
    }

    private function label(string $field): string
    {
        $label = $this->attributes[$field] ?? $field;

        return str_replace(['_', '.', '-'], ' ', $label);
    }

    private function message(string $field, string $message, ?string $parameter): string
    {
        return str_replace(
            [':label', ':other', '&amp;'],
            [$this->label($field), (string) $parameter, '&'],
            $message
        );
    }

    /** @param array<int,string> $rules */
    private function castForStorage(string $field, mixed $value, array $rules): mixed
    {
        foreach ($rules as $rule) {
            [$name] = explode(':', (string) $rule, 2);

            if (in_array($name, ['int', 'integer'], true)) {
                return (int) Str::toAsciiDigits((string) $value);
            }

            if ($name === 'numeric') {
                return (float) Str::toAsciiDigits((string) $value);
            }

            if (in_array($name, ['bool', 'boolean'], true)) {
                return in_array($value, [true, 1, '1', 'on', 'true'], true) ? 1 : 0;
            }

            if ($name === 'array') {
                return array_values((array) $value);
            }
        }

        return is_string($value) ? trim($value) : $value;
    }
}
