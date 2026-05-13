<?php
namespace amici\SuperFavourite\records;

use craft\db\ActiveRecord;
use craft\records\User;

/**
 * Collection Record
 *
 * @property int $id
 * @property int|null $userId
 * @property string $name
 * @property string $handle
 * @property string $description
 * @property bool $isDefault
 * @property string|null $allowedElementTypes
 * @property int $sortOrder
 */
class CollectionRecord extends ActiveRecord
{
    /**
     * Returns the database table name used by this Active Record.
     *
     * @return string The requested string value.
     */
    public static function tableName(): string
    {
        return '{{%super_favourite_collections}}';
    }

    /**
     * Returns the related user element, caching the lookup for this request.
     *
     * @return mixed The Craft hook response or untyped value produced by this method.
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'userId']);
    }

    /**
     * Returns Yii validation rules for this Active Record.
     *
     * @return mixed The Craft hook response or untyped value produced by this method.
     */
    public function rules()
    {
        return [
            [['name', 'handle'], 'required'],
            [['name', 'handle'], 'string', 'max' => 255],
            [['description'], 'string'],
            [['isDefault'], 'boolean'],
            [['sortOrder'], 'integer'],
        ];
    }
}

