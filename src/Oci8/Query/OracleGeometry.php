<?php

namespace Yajra\Oci8\Query;

use Stringable;

final readonly class OracleGeometry implements Stringable
{
    public function __construct(
        public string $wkt,
        public ?int $srid = null
    ) {}

    public function __toString(): string
    {
        return $this->wkt;
    }
}
