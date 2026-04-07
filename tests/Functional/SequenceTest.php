<?php

namespace Yajra\Oci8\Tests\Functional;

use Illuminate\Support\Facades\DB;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Oci8Connection;
use Yajra\Oci8\Tests\TestCase;

class SequenceTest extends TestCase
{
    #[Test]
    public function it_can_process_sequence()
    {
        /** @var Oci8Connection $connection */
        $connection = $this->getConnection();

        $sequence = $connection->getSequence();

        $sequence->create('transaction_seq');

        $transaction_id = $sequence->currentValue('transaction_seq');
        $this->assertSame(0, $transaction_id);

        $transaction_id = $sequence->nextValue('transaction_seq');
        $this->assertSame(1, $transaction_id);

        $transaction_id = $sequence->currentValue('transaction_seq');
        $this->assertSame(1, $transaction_id);

        $transaction_id = $sequence->lastInsertId('transaction_seq');
        $this->assertSame(1, $transaction_id);

        $sequence->drop('transaction_seq');
    }

    #[Test]
    public function it_can_use_sequence_not_owned_by_user()
    {
        /** @var Oci8Connection $connection */
        $connection = $this->getConnection();
        /** @var Oci8Connection $otherConnection */
        $otherConnection = DB::connection('second_connection');
        $otherSchema = $otherConnection->getConfig('username');
        $defaultSchema = $connection->getConfig('username');

        $otherConnection->statement("begin execute immediate 'drop sequence {$otherSchema}.transaction_seq'; exception when others then null; end;");

        $otherSequence = $otherConnection->getSequence();
        $otherSequence->create('transaction_seq');
        $otherConnection->statement("grant select on transaction_seq to {$defaultSchema}");

        $sequence = $connection->getSequence();
        $qualifiedSequence = "{$otherSchema}.transaction_seq";

        $transaction_id = $sequence->currentValue($qualifiedSequence);
        $this->assertSame(0, $transaction_id);

        $transaction_id = $sequence->nextValue($qualifiedSequence);
        $this->assertSame(1, $transaction_id);

        $transaction_id = $sequence->currentValue($qualifiedSequence);
        $this->assertSame(1, $transaction_id);

        $transaction_id = $sequence->lastInsertId($qualifiedSequence);
        $this->assertSame(1, $transaction_id);

        $otherSequence->drop('transaction_seq');
    }

    #[Test]
    public function it_can_use_schema_prefix()
    {
        /** @var Oci8Connection $connection */
        $connection = DB::connection('second_connection');
        $schema = strtoupper((string) $connection->getConfig('username'));

        $connection->setSchemaPrefix((string) $connection->getConfig('username'));
        $connection->enableQueryLog();

        $sequence = $connection->getSequence();
        $sequence->forceCreate('transaction_seq');

        $lastSql = array_slice($connection->getQueryLog(), -1)[0];

        $this->assertSame(
            "create sequence \"{$schema}\".\"TRANSACTION_SEQ\" minvalue 1  start with 1 increment by 1",
            $lastSql['query']
        );
        $this->assertTrue($sequence->exists('transaction_seq'));
        $this->assertSame(0, $sequence->currentValue('transaction_seq'));
        $this->assertSame(1, $sequence->nextValue('transaction_seq'));

        $sequence->drop('transaction_seq');
    }
}
