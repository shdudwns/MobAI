<?php

namespace HybridMobAI;

use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\world\World;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\entity\EntityDataHelper;

class Zombie extends Living {

    public static function getNetworkTypeId(): string {
        return "minecraft:zombie";
    }

    protected function getInitialSizeInfo(): EntitySizeInfo {
        return new EntitySizeInfo(1.95, 0.6);
    }

    /** ✅ 생성자 수정 - World 및 CompoundTag를 사용 **/
    public function __construct(World $world, CompoundTag $nbt) {
        $location = EntityDataHelper::parseLocation($nbt, $world);
        parent::__construct($location, $nbt);
    }

    public function getName(): string {
        return "Custom Zombie";
    }

    public function onUpdate(int $currentTick): bool {
        if ($this->isClosed() || !$this->isAlive()) {
            return false;
        }

        return parent::onUpdate($currentTick);
    }
}
