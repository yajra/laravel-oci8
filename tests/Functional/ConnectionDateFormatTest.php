<?php

namespace Yajra\Oci8\Tests\Functional;

use Illuminate\Support\Carbon;
use PHPUnit\Framework\Attributes\Test;
use Yajra\Oci8\Tests\TestCase;

class ConnectionDateFormatTest extends TestCase
{
    #[Test]
    public function it_can_set_the_date_format()
    {
        /** @var \Yajra\Oci8\Oci8Connection $connection */
        $connection = $this->getConnection();

        $connection->setDateFormat('YYYY-MM-DD');

        $date = $connection->select('select sysdate from dual');
        $format = Carbon::now()->format('Y-m-d');

        $this->assertSame($format, $date[0]->sysdate);

        $connection->setDateFormat('MM/DD/YYYY');
        $date = $connection->select('select sysdate from dual');
        $format = Carbon::now()->format('m/d/Y');
        $this->assertSame($format, $date[0]->sysdate);
    }
}
