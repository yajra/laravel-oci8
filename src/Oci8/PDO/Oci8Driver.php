<?php

namespace Yajra\Oci8\PDO;

use Doctrine\DBAL\Driver\AbstractOracleDriver;
use Illuminate\Database\PDO\Concerns\ConnectsToDatabase;

class Oci8Driver extends AbstractOracleDriver
{
    use ConnectsToDatabase;

    /**
     * {@inheritdoc}
     */
    public function getName(): string
    {
        return 'oci8';
    }
}
