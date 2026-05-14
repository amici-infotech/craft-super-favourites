<?php
namespace amici\SuperFavourite\jobs;

use Craft;
use craft\queue\BaseJob;
use amici\SuperFavourite\elements\FavouriteItem;

/**
 * Deletes favourite item elements for a collection that has already been deleted.
 */
class DeleteFavouriteItemsForCollection extends BaseJob
{
    public int $collectionId;

    /**
     * Removes favourites in batches so large collections do not block the delete request.
     *
     * @param $queue The queue running this job.
     *
     * @return void Nothing is returned.
     */
    public function execute($queue): void
    {
        $total = (int)FavouriteItem::find()
            ->collectionId($this->collectionId)
            ->status(null)
            ->count();

        if ($total === 0) {
            $this->setProgress($queue, 1);
            return;
        }

        $deleted = 0;

        do {
            $items = FavouriteItem::find()
                ->collectionId($this->collectionId)
                ->status(null)
                ->limit(100)
                ->all();

            foreach ($items as $item) {
                Craft::$app->getElements()->deleteElement($item);
                $deleted++;
                $this->setProgress($queue, min($deleted / $total, 1));
            }
        } while (!empty($items));
    }

    /**
     * Returns the label Craft displays in the queue manager.
     *
     * @return string The queue job description.
     */
    protected function defaultDescription(): string
    {
        return Craft::t('super-favourite', 'Deleting favourite items for deleted collection');
    }
}
