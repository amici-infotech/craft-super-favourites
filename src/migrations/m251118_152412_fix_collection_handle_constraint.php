<?php

namespace amici\SuperFavourite\migrations;

use Craft;
use craft\db\Migration;

/**
 * m251118_152412_fix_collection_handle_constraint migration.
 *
 * Fixes the unique constraint on the handle column to allow different users
 * to have collections with the same handle. The constraint now includes both
 * handle and userId to ensure uniqueness per user.
 */
class m251118_152412_fix_collection_handle_constraint extends Migration
{
    /**
     * @inheritdoc
     */
    public function safeUp(): bool
    {
        $table = '{{%super_favourite_collections}}';

        // Try to drop the old unique constraint on handle
        // We need to find the actual index name first
        try {
            // Get all indexes for this table
            $sql = "SHOW INDEXES FROM " . $this->db->quoteTableName($table) . " WHERE Key_name LIKE 'idx_%' AND Non_unique = 0 AND Column_name = 'handle'";
            $indexes = $this->db->createCommand($sql)->queryAll();

            foreach ($indexes as $index) {
                $indexName = $index['Key_name'];
                // Check if this is a single-column index on handle
                $sql = "SHOW INDEXES FROM " . $this->db->quoteTableName($table) . " WHERE Key_name = :indexName";
                $indexColumns = $this->db->createCommand($sql, ['indexName' => $indexName])->queryAll();

                if (count($indexColumns) === 1 && $indexColumns[0]['Column_name'] === 'handle') {
                    $this->dropIndex($indexName, $table);
                    echo "Dropped old unique index: {$indexName}\n";
                    break;
                }
            }
        } catch (\Exception $e) {
            echo "Warning: Could not find or drop old index: " . $e->getMessage() . "\n";
        }

        // Create a new unique constraint on handle + userId
        // This allows different users to have collections with the same handle
        // but prevents a single user from having duplicate handles
        // Note: NULL userId (global collections) will be treated as distinct values
        $this->createIndex(
            null, // Let Craft generate the name
            $table,
            ['handle', 'userId'],
            true // unique
        );

        echo "Created new unique index on (handle, userId)\n";

        return true;
    }

    /**
     * @inheritdoc
     */
    public function safeDown(): bool
    {
        $table = '{{%super_favourite_collections}}';

        // Try to drop the new constraint
        try {
            $sql = "SHOW INDEXES FROM " . $this->db->quoteTableName($table) . " WHERE Key_name LIKE 'idx_%' AND Non_unique = 0";
            $indexes = $this->db->createCommand($sql)->queryAll();

            foreach ($indexes as $index) {
                $indexName = $index['Key_name'];
                // Get all columns for this index
                $sql = "SHOW INDEXES FROM " . $this->db->quoteTableName($table) . " WHERE Key_name = :indexName";
                $indexColumns = $this->db->createCommand($sql, ['indexName' => $indexName])->queryAll();

                $columns = array_column($indexColumns, 'Column_name');
                if (count($columns) === 2 && in_array('handle', $columns) && in_array('userId', $columns)) {
                    $this->dropIndex($indexName, $table);
                    echo "Dropped compound index: {$indexName}\n";
                    break;
                }
            }
        } catch (\Exception $e) {
            echo "Warning: Could not drop compound index: " . $e->getMessage() . "\n";
        }

        // Recreate the old single-column unique constraint
        $this->createIndex(
            null,
            $table,
            'handle',
            true
        );

        return true;
    }
}
