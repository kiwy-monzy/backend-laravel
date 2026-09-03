<?php

namespace App\Support;

/**
 * One field on a module form: how to render it and how to validate it.
 *
 * **Declared once, used three times** — the form input, the validation rule
 * and the list column all read the same object, so a field that is required on
 * the form cannot quietly be optional in the controller. That drift is the
 * whole reason this exists rather than a plain array.
 */
final class Field
{
    private function __construct(
        public string $name,
        public string $label,
        public string $type = 'text',
        public bool $required = false,
        public mixed $default = null,
        public array $options = [],
        public ?string $help = null,
        public array $extraRules = [],
        public ?int $max = null,
        public ?float $min = null,
        public ?float $step = null,
    ) {
    }

    public static function text(string $name, string $label, int $max = 190): self
    {
        return new self($name, $label, 'text', max: $max);
    }

    public static function textarea(string $name, string $label, int $max = 4000): self
    {
        return new self($name, $label, 'textarea', max: $max);
    }

    public static function email(string $name, string $label): self
    {
        return new self($name, $label, 'email', max: 190);
    }

    public static function number(string $name, string $label, float $min = 0, ?float $step = 0.01): self
    {
        return new self($name, $label, 'number', min: $min, step: $step);
    }

    /** Money in major units on the form; the model stores minor units. */
    public static function money(string $name, string $label): self
    {
        return new self($name, $label, 'number', default: 0, min: 0, step: 0.01);
    }

    public static function date(string $name, string $label): self
    {
        return new self($name, $label, 'date');
    }

    /** @param array<string,string> $options value => label */
    public static function select(string $name, string $label, array $options, mixed $default = null): self
    {
        return new self($name, $label, 'select', default: $default ?? array_key_first($options), options: $options);
    }

    public static function checkbox(string $name, string $label, bool $default = false): self
    {
        return new self($name, $label, 'checkbox', default: $default);
    }

    public function required(bool $required = true): self
    {
        $this->required = $required;

        return $this;
    }

    public function default(mixed $value): self
    {
        $this->default = $value;

        return $this;
    }

    public function help(string $help): self
    {
        $this->help = $help;

        return $this;
    }

    public function rules(array $extra = []): array
    {
        $rules = [$this->required ? 'required' : 'nullable'];

        $rules[] = match ($this->type) {
            'email' => 'email',
            'number' => 'numeric',
            'date' => 'date',
            'checkbox' => 'boolean',
            'select' => 'in:' . implode(',', array_keys($this->options)),
            default => 'string',
        };

        if ($this->max !== null && in_array($this->type, ['text', 'textarea', 'email'], true)) {
            $rules[] = 'max:' . $this->max;
        }

        if ($this->min !== null && $this->type === 'number') {
            $rules[] = 'min:' . $this->min;
        }

        return array_merge($rules, $this->extraRules, $extra);
    }
}
