<?php

namespace App\Support\Database;

use Illuminate\Database\Schema\Grammars\SqlServerGrammar;

/**
 * Schema grammar that also works on SQL Server 2014/2016.
 *
 * Laravel lists index and foreign-key columns with STRING_AGG, which only
 * exists from SQL Server 2017. The hospital's database server is 2014, so
 * Schema::getIndexes() / getForeignKeys() (used by the API documentation
 * generator, model:show and some migrations) failed. These versions build
 * the same comma-separated, ordered lists with FOR XML PATH, which every
 * supported SQL Server version understands.
 */
class SqlServerSchemaGrammar extends SqlServerGrammar
{
    public function compileIndexes($schema, $table)
    {
        return sprintf(
            'select idx.name as name, '
            .'stuff((select \',\' + col.name from sys.index_columns as idxcol '
            .'join sys.columns as col on idxcol.object_id = col.object_id and idxcol.column_id = col.column_id '
            .'where idxcol.object_id = idx.object_id and idxcol.index_id = idx.index_id '
            .'order by idxcol.key_ordinal '
            ."for xml path(''), type).value('.', 'nvarchar(max)'), 1, 1, '') as columns, "
            .'idx.type_desc as [type], idx.is_unique as [unique], idx.is_primary_key as [primary] '
            .'from sys.indexes as idx '
            .'join sys.tables as tbl on idx.object_id = tbl.object_id '
            .'join sys.schemas as scm on tbl.schema_id = scm.schema_id '
            .'where tbl.name = %s and scm.name = %s and idx.index_id > 0 '
            .'and exists (select 1 from sys.index_columns as ic where ic.object_id = idx.object_id and ic.index_id = idx.index_id)',
            $this->quoteString($table),
            $schema ? $this->quoteString($schema) : 'schema_name()',
        );
    }

    public function compileForeignKeys($schema, $table)
    {
        $list = fn (string $side) => 'stuff((select \',\' + c.name from sys.foreign_key_columns as k '
            ."join sys.columns as c on k.{$side}_object_id = c.object_id and k.{$side}_column_id = c.column_id "
            .'where k.constraint_object_id = fk.object_id order by k.constraint_column_id '
            ."for xml path(''), type).value('.', 'nvarchar(max)'), 1, 1, '')";

        return sprintf(
            'select fk.name as name, '
            .$list('parent').' as columns, '
            .'fs.name as foreign_schema, ft.name as foreign_table, '
            .$list('referenced').' as foreign_columns, '
            .'fk.update_referential_action_desc as on_update, '
            .'fk.delete_referential_action_desc as on_delete '
            .'from sys.foreign_keys as fk '
            .'join sys.tables as lt on lt.object_id = fk.parent_object_id '
            .'join sys.schemas as ls on lt.schema_id = ls.schema_id '
            .'join sys.tables as ft on ft.object_id = fk.referenced_object_id '
            .'join sys.schemas as fs on ft.schema_id = fs.schema_id '
            .'where lt.name = %s and ls.name = %s',
            $this->quoteString($table),
            $schema ? $this->quoteString($schema) : 'schema_name()',
        );
    }
}
