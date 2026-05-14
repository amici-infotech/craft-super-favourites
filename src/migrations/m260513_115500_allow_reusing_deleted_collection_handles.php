<?php

namespace amici\SuperFavourite\migrations;

use craft\db\Migration;

/**
 * Allows handles from soft-deleted collections to be reused.
 */
class m260513_115500_allow_reusing_deleted_collection_handles extends Migration
{
    /**
     * Removes database-level uniqueness from collection handles.
     *
     * @return bool True when the migration completes successfully.
     */
    public function safeUp(): bool
    {
        $table = '{{%super_favourite_collections}}';

        foreach ($this->handleUniqueIndexes($table) as $indexName) {
            $this->dropIndex($indexName, $table);
        }

        if (empty($this->handleIndexes($table))) {
            $this->createIndex(null, $table, 'handle', false);
        }

        return true;
    }

    /**
     * Restores a unique handle index when rolling back.
     *
     * @return bool True when the rollback completes successfully.
     */
    public function safeDown(): bool
    {
        $table = '{{%super_favourite_collections}}';

        foreach ($this->handleIndexes($table) as $indexName) {
            $this->dropIndex($indexName, $table);
        }

        $this->createIndex(null, $table, 'handle', true);

        return true;
    }

    /**
     * Finds unique indexes that include the handle column.
     *
     * @param string $table The table to inspect.
     *
     * @return array Index names.
     */
    private function handleUniqueIndexes(string $table): array
    {
        $indexes = $this->tableIndexes($table);
        $matches = [];

        foreach ($indexes as $indexName => $index) {
            if ($index['unique'] && in_array('handle', $index['columns'], true)) {
                $matches[] = $indexName;
            }
        }

        return $matches;
    }

    /**
     * Finds all non-primary indexes that include the handle column.
     *
     * @param string $table The table to inspect.
     *
     * @return array Index names.
     */
    private function handleIndexes(string $table): array
    {
        $indexes = $this->tableIndexes($table);
        $matches = [];

        foreach ($indexes as $indexName => $index) {
            if ($indexName !== 'PRIMARY' && in_array('handle', $index['columns'], true)) {
                $matches[] = $indexName;
            }
        }

        return $matches;
    }

    /**
     * Returns index metadata keyed by index name.
     *
     * @param string $table The table to inspect.
     *
     * @return array Index metadata.
     */
    private function tableIndexes(string $table): array
    {
        $rows = $this->db
            ->createCommand('SHOW INDEXES FROM ' . $this->db->quoteTableName($table))
            ->queryAll();

        $indexes = [];

        foreach ($rows as $row) {
            $indexName = $row['Key_name'];
            $indexes[$indexName] ??= [
                'unique' => (int)$row['Non_unique'] === 0,
                'columns' => [],
            ];
            $indexes[$indexName]['columns'][] = $row['Column_name'];
        }

        return $indexes;
    }
}
