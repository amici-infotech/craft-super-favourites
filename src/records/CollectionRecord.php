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
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%super_favourite_collections}}';
    }

    /**
     * Define relations
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'userId']);
    }

    /**
     * Define validation rules
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

