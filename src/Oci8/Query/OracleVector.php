<?php

namespace Yajra\Oci8\Query;

use Illuminate\Contracts\Support\Arrayable;
use JsonException;
use Stringable;

final readonly class OracleVector implements Stringable
{
    private string $value;

    /**
     * @param  Arrayable<int, float|int>|array<int, float|int>  $vector
     *
     * @throws JsonException
     */
    public function __construct(Arrayable|array $vector)
    {
        $this->value = json_encode(
            $vector instanceof Arrayable ? $vector->toArray() : $vector,
            flags: JSON_THROW_ON_ERROR
        );
    }

    public function __toString(): string
    {
        return $this->value;
    }
}
