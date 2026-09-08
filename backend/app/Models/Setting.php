<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Setting extends Model
{
    public const TYPE_STRING = 'string';

    public const TYPE_INTEGER = 'integer';

    public const TYPE_BOOLEAN = 'boolean';

    public const TYPE_JSON = 'json';

    protected $primaryKey = 'key';

    public $incrementing = false;

    protected $keyType = 'string';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'value',
        'type',
        'group',
    ];

    /**
     * Decode the stored raw value according to its declared type.
     */
    public function decodedValue(): mixed
    {
        return match ($this->type) {
            self::TYPE_INTEGER => (int) $this->value,
            self::TYPE_BOOLEAN => filter_var($this->value, FILTER_VALIDATE_BOOLEAN),
            self::TYPE_JSON => json_decode((string) $this->value, true),
            default => $this->value,
        };
    }

    /**
     * Encode a runtime value for storage.
     */
    public static function encodeValue(mixed $value, string $type): ?string
    {
        if ($value === null) {
            return null;
        }

        return match ($type) {
            self::TYPE_BOOLEAN => $value ? '1' : '0',
            self::TYPE_JSON => json_encode($value),
            default => (string) $value,
        };
    }
}
