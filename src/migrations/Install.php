<?php
namespace amici\SuperFavourite\migrations;

use Craft;
use craft\db\Migration;
use amici\SuperFavourite\elements\Collection;

/**
 * Install migration for Super Favourite plugin
 *
 * Database Architecture:
 *
 * 1. super_favourite_collections - Stores user-created collections/lists
 *    - id (PK, FK to elements)
 *    - userId (FK to users) - Owner of the collection
 *    - name - Collection name
 *    - handle - Unique handle for the collection
 *    - description - Optional description
 *    - isDefault - Whether this is the user's default collection
 *    - sortOrder - For ordering collections
 *
 * 2. super_favourite_items - Stores individual favourite items
 *    - id (PK, FK to elements)
 *    - userId (FK to users) - User who favourited
 *    - collectionId (FK to super_favourite_collections) - Which collection it belongs to
 *    - elementId (FK to elements) - The element being favourited
 *    - elementType - Class name of the element type (entry, user, category, asset, etc.)
 *    - sortOrder - For custom ordering within collection
 *    - notes - Optional user notes about this favourite
 */
class Install extends Migration
{
    /**
     * Runs the migration upgrade steps.
     *
     * @return mixed True when the migration completes successfully.
     */
    public function safeUp()
    {
        $this->createTables();
        $this->createIndexes();
        $this->addForeignKeys();
        $this->createDefaultCollection();

        return true;
    }

    /**
     * Creates the plugin tables for collections and favourite items.
     *
     * @return mixed The Craft hook response or untyped value produced by this method.
     */
    protected function createTables()
    {
        // Collections table - stores global and user-created lists/collections
        // userId is null for global collections, set for user-specific collections
        $this->createTable('{{%super_favourite_collections}}', [
            'id'                  => $this->primaryKey(),
            'userId'              => $this->integer()->null(), // Null = global collection
            'name'                => $this->string()->notNull(),
            'handle'              => $this->string()->notNull(),
            'description'         => $this->text(),
            'isDefault'           => $this->boolean()->defaultValue(false),
            'allowedElementTypes' => $this->text(), // JSON array of allowed element types
            'sortOrder'           => $this->integer()->defaultValue(0),
            'dateCreated'         => $this->dateTime()->notNull(),
            'dateUpdated'         => $this->dateTime()->notNull(),
            'uid'                 => $this->uid(),
        ]);

        // Favourite items table - stores individual favourites
        $this->createTable('{{%super_favourite_items}}', [
            'id'           => $this->primaryKey(),
            'userId'       => $this->integer()->notNull(),
            'collectionId' => $this->integer()->notNull(),
            'elementId'    => $this->integer()->notNull(),
            'elementType'  => $this->string()->notNull(),
            'sortOrder'    => $this->integer()->defaultValue(0),
            'notes'        => $this->text(),
            'dateCreated'  => $this->dateTime()->notNull(),
            'dateUpdated'  => $this->dateTime()->notNull(),
            'uid'          => $this->uid(),
        ]);
    }

    /**
     * Adds database relationships between plugin tables and Craft tables.
     *
     * @return void Nothing is returned.
     */
    public function addForeignKeys(): void
    {
        // Collections foreign keys
        $this->addForeignKey(
            null,
            '{{%super_favourite_collections}}',
            'id',
            '{{%elements}}',
            'id',
            'CASCADE'
        );

        // userId can be null for global collections, so use SET NULL instead of CASCADE
        $this->addForeignKey(
            null,
            '{{%super_favourite_collections}}',
            'userId',
            '{{%users}}',
            'id',
            'SET NULL'
        );

        // Favourite items foreign keys
        $this->addForeignKey(
            null,
            '{{%super_favourite_items}}',
            'id',
            '{{%elements}}',
            'id',
            'CASCADE'
        );

        $this->addForeignKey(
            null,
            '{{%super_favourite_items}}',
            'userId',
            '{{%users}}',
            'id',
            'CASCADE'
        );

        $this->addForeignKey(
            null,
            '{{%super_favourite_items}}',
            'collectionId',
            '{{%super_favourite_collections}}',
            'id',
            'CASCADE'
        );

        $this->addForeignKey(
            null,
            '{{%super_favourite_items}}',
            'elementId',
            '{{%elements}}',
            'id',
            'CASCADE'
        );
    }

    /**
     * Adds indexes used for uniqueness and common lookups.
     *
     * @return void Nothing is returned.
     */
    public function createIndexes(): void
    {
        // Index for faster lookups by user
        $this->createIndex(
            null,
            '{{%super_favourite_collections}}',
            'userId',
            false
        );

        // Non-unique index for handle lookups. Active-handle uniqueness is enforced in PHP
        // so soft-deleted collections do not reserve their old handles.
        $this->createIndex(
            null,
            '{{%super_favourite_collections}}',
            'handle',
            false
        );

        // Index for default collection lookup
        $this->createIndex(
            null,
            '{{%super_favourite_collections}}',
            ['userId', 'isDefault'],
            false
        );

        // Index for faster lookups by user
        $this->createIndex(
            null,
            '{{%super_favourite_items}}',
            'userId',
            false
        );

        // Index for collection lookups
        $this->createIndex(
            null,
            '{{%super_favourite_items}}',
            'collectionId',
            false
        );

        // Index for element lookups
        $this->createIndex(
            null,
            '{{%super_favourite_items}}',
            'elementId',
            false
        );

        // Unique index to prevent duplicate favourites (user + collection + element)
        $this->createIndex(
            null,
            '{{%super_favourite_items}}',
            ['userId', 'collectionId', 'elementId'],
            true
        );

        // Index for element type filtering
        $this->createIndex(
            null,
            '{{%super_favourite_items}}',
            'elementType',
            false
        );
    }

    /**
     * Creates the global default collection used as a fallback.
     *
     * @return void Nothing is returned.
     */
    protected function createDefaultCollection(): void
    {
        $collection = new Collection();
        $collection->name = 'Default';
        $collection->handle = 'default';
        $collection->description = 'Default collection for favourites';
        $collection->isDefault = true;
        $collection->userId = null; // Global collection
        $collection->allowedElementTypes = []; // Allow all element types
        $collection->sortOrder = 0;

        if (!Craft::$app->getElements()->saveElement($collection)) {
            Craft::warning('Could not create default collection: ' . implode(', ', $collection->getErrors()), __METHOD__);
        }
    }

    /**
     * Runs the migration rollback steps.
     *
     * @return mixed True when the rollback completes successfully when the method returns a value.
     */
    public function safeDown()
    {
        $db = Craft::$app->getDb();

        // Check if tables exist before trying to delete elements
        $collectionsTableExists = $db->tableExists('{{%super_favourite_collections}}');
        $itemsTableExists = $db->tableExists('{{%super_favourite_items}}');

        // Delete elements only if tables exist
        if ($collectionsTableExists || $itemsTableExists) {
            // Delete element records from Craft's elements table
            $this->_deleteElementRecords('amici\SuperFavourite\elements\Collection');
            $this->_deleteElementRecords('amici\SuperFavourite\elements\FavouriteItem');
        }

        // Drop foreign keys first
        if ($itemsTableExists) {
        $this->dropAllForeignKeysToTable('{{%super_favourite_items}}');
        }
        if ($collectionsTableExists) {
        $this->dropAllForeignKeysToTable('{{%super_favourite_collections}}');
        }

        // Drop tables
        $this->dropTableIfExists('{{%super_favourite_items}}');
        $this->dropTableIfExists('{{%super_favourite_collections}}');

        // Clear all caches to remove any cached element data
        Craft::$app->getCache()->flush();
    }

    /**
     * Deletes Craft element rows for a plugin element type during uninstall cleanup.
     *
     * @param string $elementType The fully qualified class name of the Craft element type.
     *
     * @return void Nothing is returned.
     */
    private function _deleteElementRecords(string $elementType): void
    {
        $db = Craft::$app->getDb();

        // Delete from elements table directly
        $db->createCommand()
            ->delete('{{%elements}}', ['type' => $elementType])
            ->execute();
    }
}

