<?php
/**
 * SB-Tech — QueryException tests.
 *
 * Guards the security property that raw SQL never appears in getMessage()
 * (operation handlers flash $e->getMessage() to the browser), while remaining
 * available to logs and developers via getSql().
 */

use PHPUnit\Framework\TestCase;

class QueryExceptionTest extends TestCase
{
    public function testMessageDoesNotContainSql()
    {
        $e = new QueryException('Database query failed: unknown column', 'SELECT * FROM `tbl_secrets` WHERE token = ?');

        $this->assertStringNotContainsString('SELECT', $e->getMessage());
        $this->assertStringNotContainsString('tbl_secrets', $e->getMessage());
        $this->assertSame('Database query failed: unknown column', $e->getMessage());
    }

    public function testGetSqlReturnsFailedSql()
    {
        $sql = 'INSERT INTO `tbl_documents` (`document_number`) VALUES (?)';
        $e = new QueryException('Database query failed: duplicate entry', $sql);

        $this->assertSame($sql, $e->getSql());
    }

    public function testPreviousExceptionIsPreserved()
    {
        $prev = new RuntimeException('driver-level detail');
        $e = new QueryException('Database query failed: boom', 'SELECT 1', 0, $prev);

        $this->assertSame($prev, $e->getPrevious());
    }
}