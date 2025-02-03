<?php

namespace HybridMobAI;

use pocketmine\entity\Monster;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\World;
use pocketmine\entity\Location;

class Zombie extends Monster {

    public function __construct(Location $location, CompoundTag $nbt) {
        parent::__construct($location, $nbt);
    }

    public function onSpawn(): void {
        parent::onSpawn();
        $this->getServer()->getLogger()->info("좀비 스폰 완료: " . $this->getName());
    }

    public function onUpdate(int $currentTick): bool {
        $this->getServer()->getLogger()->info("좀비 업데이트 중: " . $this->getName());
        return parent::onUpdate($currentTick);
    }
}
