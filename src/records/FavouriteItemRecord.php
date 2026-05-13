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
     * Returns the database table name used by this Active Record.
     *
     * @return string The requested string value.
     */
    public static function tableName(): string
    {
        return '{{%super_favourite_items}}';
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
     * Returns the related collection element, caching the lookup for this request.
     *
     * @return mixed The Craft hook response or untyped value produced by this method.
     */
    public function getCollection()
    {
        return $this->hasOne(CollectionRecord::class, ['id' => 'collectionId']);
    }

    /**
     * Returns the element value.
     *
     * @return mixed The Craft hook response or untyped value produced by this method.
     */
    public function getElement()
    {
        return $this->hasOne(Element::class, ['id' => 'elementId']);
    }

    /**
     * Returns Yii validation rules for this Active Record.
     *
     * @return mixed The Craft hook response or untyped value produced by this method.
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

