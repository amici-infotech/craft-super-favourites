<?php
namespace amici\SuperFavourite\migrations;

use Craft;
use craft\db\Migration;

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
    public function safeUp()
    {
        $this->createTables();
        $this->createIndexes();
        $this->addForeignKeys();
        $this->createDefaultCollection();

        return true;
    }

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

    public function createIndexes(): void
    {
        // Index for faster lookups by user
        $this->createIndex(
            null,
            '{{%super_favourite_collections}}',
            'userId',
            false
        );

        // Unique index for handle (global collections have unique handles)
        $this->createIndex(
            null,
            '{{%super_favourite_collections}}',
            'handle',
            true
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
     * Create the default global collection
     */
    protected function createDefaultCollection(): void
    {
        $collection = new \amici\SuperFavourite\elements\Collection();
        $collection->name = 'Default';
        $collection->handle = 'default';
        $collection->description = 'Default collection for favourites';
        $collection->isDefault = true;
        $collection->userId = null; // Global collection
        $collection->allowedElementTypes = null; // Allow all element types
        $collection->sortOrder = 0;

        if (!Craft::$app->getElements()->saveElement($collection)) {
            Craft::warning('Could not create default collection: ' . implode(', ', $collection->getErrors()), __METHOD__);
        }
    }

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
     * Delete element records from elements table
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

