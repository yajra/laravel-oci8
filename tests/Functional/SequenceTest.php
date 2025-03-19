<?php

namespace Yajra\Oci8\Tests\Functional;

use Illuminate\Foundation\Testing\DatabaseTransactions;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class SequenceTest extends TestCase
{
    use DatabaseTransactions;

    #[Test]
    public function it_can_process_sequence()
    {
        /** @var \Yajra\Oci8\Oci8Connection $connection */
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
        /** @var \Yajra\Oci8\Oci8Connection $connection */
        $connection = $this->getConnection();

        try {
            $connection->statement('alter session set "_oracle_script"=true');
            $connection->statement('grant all privileges to demo identified by oracle container=ALL');
        } catch (\Exception) {
            $connection->statement('grant all privileges to demo identified by oracle');
        }

        $sequence = $connection->getSequence();

        $sequence->create('demo.transaction_seq');

        $transaction_id = $sequence->currentValue('demo.transaction_seq');
        $this->assertSame(0, $transaction_id);

        $transaction_id = $sequence->nextValue('demo.transaction_seq');
        $this->assertSame(1, $transaction_id);

        $transaction_id = $sequence->currentValue('demo.transaction_seq');
        $this->assertSame(1, $transaction_id);

        $transaction_id = $sequence->lastInsertId('demo.transaction_seq');
        $this->assertSame(1, $transaction_id);

        $sequence->drop('demo.transaction_seq');
    }

    #[Test]
    public function it_can_use_schema_prefix()
    {
        /** @var \Yajra\Oci8\Oci8Connection $connection */
        $connection = $this->getConnection();
        try {
            $connection->statement('alter session set "_oracle_script"=true');
            $connection->statement('grant all privileges to demo identified by oracle container=ALL');
        } catch (\Exception) {
            $connection->statement('grant all privileges to demo identified by oracle');
        }

        $connection->setSchemaPrefix('demo');
        $connection->enableQueryLog();

        $sequence = $connection->getSequence();
        $sequence->forceCreate('transaction_seq');

        $lastSql = array_slice($connection->getQueryLog(), -1)[0];

        $this->assertSame(
            'create sequence "DEMO"."TRANSACTION_SEQ" minvalue 1  start with 1 increment by 1',
            $lastSql['query']
        );
        $this->assertTrue($sequence->exists('transaction_seq'));
        $this->assertSame(0, $sequence->currentValue('transaction_seq'));
        $this->assertSame(1, $sequence->nextValue('transaction_seq'));

        $sequence->drop('transaction_seq');
    }
}
