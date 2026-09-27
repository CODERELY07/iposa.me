<?php

namespace App\Exports;

use Illuminate\Database\ConnectionInterface;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * The whole database as plain INSERT statements, ready to paste into Supabase's
 * SQL editor (or any other client) after the migrations have been run there.
 *
 * Data only, never schema: the tables come from `php artisan migrate`, so a
 * restore is "migrate, then paste this", and the file can't recreate an old
 * shape over a newer one.
 */
class DatabaseBackup
{
    /**
     * Tables that only hold throwaway state: logins, cached values, queued jobs.
     * Restoring them would just log everyone out again, so they are left out.
     *
     * @var list<string>
     */
    public const SKIPPED = ['sessions', 'cache', 'cache_locks', 'jobs', 'job_batches', 'failed_jobs', 'password_reset_tokens'];

    /**
     * Rows read (and written) at a time, so a big shop never fills memory.
     */
    private const CHUNK = 500;

    /**
     * The file, one line at a time.
     *
     * @return iterable<int, string>
     */
    public function lines(): iterable
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $tables = $this->tables();

        yield from $this->header($driver, $tables);
        yield 'BEGIN;';
        yield '';

        foreach ($tables as $table) {
            yield from $this->tableLines($connection, $driver, $table);
        }

        yield from $this->linkAccountsToShops($connection);
        yield from $this->resetSequences($connection, $driver, $tables);

        yield 'COMMIT;';
        yield '';
        yield '-- End of backup.';
    }

    public function filename(): string
    {
        return 'iposa-backup-'.now()->format('Y-m-d-Hi').'.sql';
    }

    /**
     * Every table with rows worth keeping, parents before children so the
     * inserts line up with the foreign keys.
     *
     * @return list<string>
     */
    public function tables(): array
    {
        $existing = collect(Schema::getTables())
            ->pluck('name')
            ->map(fn (string $name) => str_contains($name, '.') ? substr($name, strrpos($name, '.') + 1) : $name)
            ->reject(fn (string $name) => in_array($name, [...self::SKIPPED, 'migrations'], true))
            ->values();

        // The order rows depend on each other in; anything new lands after these.
        $known = ['users', 'plans', 'businesses', 'categories', 'items', 'item_containers', 'item_variants', 'recipe_lines',
            'orders', 'order_lines', 'audits', 'audit_lines', 'stock_movements', 'assets', 'expenses', 'subscription_payments'];

        return $existing
            ->sortBy(fn (string $name) => ($index = array_search($name, $known, true)) === false ? count($known) : $index)
            ->values()
            ->all();
    }

    /**
     * @param  list<string>  $tables
     * @return iterable<int, string>
     */
    private function header(string $driver, array $tables): iterable
    {
        yield '-- iPOSa database backup';
        yield '-- Taken: '.now()->toDayDateTimeString().' ('.config('app.timezone').')';
        yield '-- Source: '.$driver.' · '.count($tables).' tables';
        yield '--';
        yield '-- To restore: run `php artisan migrate` on the empty database first,';
        yield '-- then paste this whole file into the SQL editor and run it.';
        yield '-- It only inserts rows; it never creates or drops tables.';
        yield '';
    }

    /**
     * @return iterable<int, string>
     */
    private function tableLines(ConnectionInterface $connection, string $driver, string $table): iterable
    {
        $columns = array_column(Schema::getColumns($table), 'name');

        if ($columns === []) {
            return;
        }

        // A shop points at its owner and the owner at the shop. Inserting the
        // accounts without their shop, then linking them at the end, means the
        // file needs no privileges to switch foreign keys off.
        $deferred = $table === 'users' ? 'business_id' : null;

        $grammar = $connection->getQueryGrammar();
        $wrappedTable = $grammar->wrapTable($table);
        $wrappedColumns = implode(', ', array_map(fn (string $column) => $grammar->wrap($column), $columns));
        $rows = 0;

        foreach ($connection->table($table)->orderBy($columns[0])->lazy(self::CHUNK) as $row) {
            if ($rows === 0) {
                yield '-- '.$table;
            }

            $values = implode(', ', array_map(function (string $column) use ($connection, $driver, $row, $deferred): string {
                $value = ((array) $row)[$column] ?? null;

                return $this->literal($connection, $driver, $column === $deferred ? null : $value);
            }, $columns));

            yield "INSERT INTO {$wrappedTable} ({$wrappedColumns}) VALUES ({$values});";
            $rows++;
        }

        yield $rows === 0 ? "-- {$table}: no rows" : '';
    }

    /**
     * Put each account back in its shop, once both tables are in.
     *
     * @return iterable<int, string>
     */
    private function linkAccountsToShops(ConnectionInterface $connection): iterable
    {
        $grammar = $connection->getQueryGrammar();
        $users = $connection->table('users')->whereNotNull('business_id')->orderBy('id')->get(['id', 'business_id']);

        if ($users->isEmpty()) {
            return;
        }

        yield '-- Put each account back in its shop.';

        foreach ($users as $user) {
            yield 'UPDATE '.$grammar->wrapTable('users').' SET '.$grammar->wrap('business_id')." = {$user->business_id} WHERE ".$grammar->wrap('id')." = {$user->id};";
        }

        yield '';
    }

    /**
     * After inserting rows with their own ids, the id counter has to be moved
     * past them, or the next sale collides with an existing row.
     *
     * @param  list<string>  $tables
     * @return iterable<int, string>
     */
    private function resetSequences(ConnectionInterface $connection, string $driver, array $tables): iterable
    {
        if ($driver !== 'pgsql') {
            return;
        }

        yield '-- Move each id counter past the rows above.';

        foreach ($tables as $table) {
            if (! in_array('id', array_column(Schema::getColumns($table), 'name'), true)) {
                continue;
            }

            $quoted = $this->quote($connection, $table);
            yield "SELECT setval(pg_get_serial_sequence({$quoted}, 'id'), COALESCE((SELECT MAX(id) FROM ".$connection->getQueryGrammar()->wrapTable($table).'), 1), true);';
        }

        yield '';
    }

    /**
     * One value, written the way this database reads it back.
     */
    private function literal(ConnectionInterface $connection, string $driver, mixed $value): string
    {
        if ($value === null) {
            return 'NULL';
        }

        if (is_bool($value)) {
            return $driver === 'pgsql' ? ($value ? 'true' : 'false') : ($value ? '1' : '0');
        }

        if (is_int($value) || is_float($value)) {
            return (string) $value;
        }

        if (is_resource($value)) {
            $value = stream_get_contents($value);
        }

        return $this->quote($connection, (string) $value);
    }

    private function quote(ConnectionInterface $connection, string $value): string
    {
        return $connection->getPdo()->quote($value);
    }
}
