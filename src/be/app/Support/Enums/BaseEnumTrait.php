<?php

declare(strict_types=1);

namespace App\Support\Enums;

/**
 * @method static cases()
 * @method static from(BaseEnumTrait|int $value)
 */
trait BaseEnumTrait
{
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    public static function names(): array
    {
        return array_column(self::cases(), 'name');
    }

    public static function fromValue(int|self $value): self
    {
        return $value instanceof self ? $value : self::from($value);
    }

    /**
     * @throws \JsonException
     */
    public static function toArray(): array
    {
        return array_map(static function (self $type) {
            return [
                'value' => $type->value,
                'label' => method_exists($type, 'label') ? $type->label() : $type->name,
            ];
        }, self::cases());
    }
}
