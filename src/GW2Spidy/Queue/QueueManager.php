<?php

namespace GW2Spidy\Queue;

use \Propel;
use GW2Spidy\Util\Singleton;
use GW2Spidy\DB\ItemType;
use GW2Spidy\DB\ItemQuery;
use GW2Spidy\Util\RedisQueue\RedisQueueManager;
use GW2Spidy\Util\RedisQueue\RedisPriorityQueueManager;

class QueueManager extends Singleton {
    public function getItemListingsQueueManager() {
        return new RedisPriorityQueueManager('item-listings-queue');
    }
    public function getItemQueueManager() {
        return new RedisQueueManager('items-queue');
    }

    public function enqueueItemListingWorkersDB($type = null) {
        Propel::disableInstancePooling();

        $q = ItemQuery::create();
        $queueManager = $this->getItemListingsQueueManager();

        if ($type instanceof ItemType) {
            $q->filterByType($type);
        } else if (is_numeric($type)) {
            $q->filterByTypeId($type);
        }

        $i = 0;
        foreach ($q->find() as $item) {
            $queueItem = new ItemListingsQueueItem($item);
            $queueManager->enqueue($queueItem);

            unset($item, $queueItem);

            var_dump($i++);

            if ($i > 5) {
                return;
            }
        }
    }
}

?>
