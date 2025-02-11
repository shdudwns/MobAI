<?php

namespace HybridMobAI;

use pocketmine\event\Listener;
use pocketmine\event\entity\EntityDeathEvent;

class EntityEventListener implements Listener {
    private EntityAI $entityAI;

    public function __construct(EntityAI $entityAI) {
        $this->entityAI = $entityAI;
    }

    public function onEntityDeath(EntityDeathEvent $event): void {
        $entity = $event->getEntity();
        if ($entity instanceof Living) {
            $this->entityAI->onMobDeath($entity);
        }
    }
}
