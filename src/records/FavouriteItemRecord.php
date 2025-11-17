<?php
namespace amici\SuperFavourite\records;

use craft\db\ActiveRecord;
use craft\records\User;
use craft\records\Element;

/**
 * Favourite Item Record
 *
 * @property int $id
 * @property int $userId
 * @property int $collectionId
 * @property int $elementId
 * @property string $elementType
 * @property int $sortOrder
 * @property string $notes
 */
class FavouriteItemRecord extends ActiveRecord
{
    /**
     * @inheritdoc
     */
    public static function tableName(): string
    {
        return '{{%super_favourite_items}}';
    }

    /**
     * Define relations
     */
    public function getUser()
    {
        return $this->hasOne(User::class, ['id' => 'userId']);
    }

    public function getCollection()
    {
        return $this->hasOne(CollectionRecord::class, ['id' => 'collectionId']);
    }

    public function getElement()
    {
        return $this->hasOne(Element::class, ['id' => 'elementId']);
    }

    /**
     * Define validation rules
     */
    public function rules()
    {
        return [
            [['userId', 'collectionId', 'elementId', 'elementType'], 'required'],
            [['elementType'], 'string', 'max' => 255],
            [['notes'], 'string'],
            [['sortOrder'], 'integer'],
        ];
    }
}

