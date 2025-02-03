<?php

namespace HybridMobAI;

use pocketmine\entity\Living;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\World;
use pocketmine\entity\Location;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\Server;

class Zombie extends Living {

    public function __construct(Location $location, CompoundTag $nbt) {
        parent::__construct($location, $nbt);
    }

    public function onSpawn(): void {
        parent::onSpawn();
        Server::getInstance()->getLogger()->info("좀비 스폰 완료: " . $this->getName());
    }

    public function onUpdate(int $currentTick): bool {
        Server::getInstance()->getLogger()->info("좀비 업데이트 중: " . $this->getName());
        return parent::onUpdate($currentTick);
    }

    public function getName(): string {
        return "Zombie";
    }

    protected function getInitialSizeInfo(): EntitySizeInfo {
        return new EntitySizeInfo(1.8, 0.6); // 높이와 너비를 설정
    }

    public static function getNetworkTypeId(): string {
        return "minecraft:zombie";
    }
}
