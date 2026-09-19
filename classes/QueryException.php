<?php
/**
 * SB-Tech — Database query exception.
 *
 * Carries the failed SQL for diagnostic logging via getSql(), but keeps it OUT
 * of getMessage() so raw SQL never reaches user-facing flash messages (many
 * operation handlers flash $e->getMessage() verbatim).
 */

class QueryException extends RuntimeException
{
    private string $sql;

    public function __construct(string $message, string $sql, int $code = 0, ?Throwable $previous = null)
    {
        $this->sql = $sql;
        parent::__construct($message, $code, $previous);
    }

    public function getSql(): string
    {
        return $this->sql;
    }
}