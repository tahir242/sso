<?php

namespace App\Support\Database;

use Illuminate\Database\SqlServerConnection as BaseConnection;
use PDOStatement;

/**
 * SQL Server connection that returns BIGINT columns as integers.
 *
 * The pdo_sqlsrv driver returns BIGINT as strings (even with
 * SQLSRV_ATTR_FETCHES_NUMERIC_TYPE), while MySQL, PostgreSQL and SQLite
 * return integers. Laravel's ids and foreign keys are BIGINT, and the code
 * compares ids strictly (`$ticket->assignee_id === $user->getKey()`), so
 * without this the same code would behave differently on SQL Server.
 */
class SqlServerConnection extends BaseConnection
{
    /** Index and foreign-key queries that also run on SQL Server 2014/2016. */
    protected function getDefaultSchemaGrammar()
    {
        return new SqlServerSchemaGrammar($this);
    }

    public function select($query, $bindings = [], $useReadPdo = true, array $fetchUsing = [])
    {
        return $this->run($query, $bindings, function ($query, $bindings) use ($useReadPdo, $fetchUsing) {
            if ($this->pretending()) {
                return [];
            }

            $statement = $this->prepared($this->getPdoForSelect($useReadPdo)->prepare($query));
            $this->bindValues($statement, $this->prepareBindings($bindings));
            $statement->execute();

            $bigints = $this->bigintColumns($statement);
            $rows = $statement->fetchAll(...$fetchUsing);

            if ($bigints === []) {
                return $rows;
            }

            foreach ($rows as $i => $row) {
                foreach ($bigints as $column) {
                    if (is_object($row) && isset($row->{$column}) && is_string($row->{$column})) {
                        $row->{$column} = (int) $row->{$column};
                    } elseif (is_array($row) && isset($row[$column]) && is_string($row[$column])) {
                        $rows[$i][$column] = (int) $row[$column];
                    }
                }
            }

            return $rows;
        });
    }

    /**
     * @return array<int, string> names of the result's BIGINT columns
     */
    private function bigintColumns(PDOStatement $statement): array
    {
        $columns = [];

        for ($i = 0; $i < $statement->columnCount(); $i++) {
            $meta = $statement->getColumnMeta($i);

            if ($meta !== false && strtolower((string) ($meta['sqlsrv:decl_type'] ?? '')) === 'bigint') {
                $columns[] = $meta['name'];
            }
        }

        return $columns;
    }
}
