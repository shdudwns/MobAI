<?php

namespace HybridMobAI;

use pocketmine\entity\Monster;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\World;

class Zombie extends Monster {

    public function __construct(World $world, CompoundTag $nbt) {
        parent::__construct($world, $nbt);
        $this->getLogger()->info("좀비 생성 완료: " . $this->getName());
    }

    public function onSpawn(): void {
        parent::onSpawn();
        $this->getLogger()->info("좀비 스폰 완료: " . $this->getName());
    }

    public function onUpdate(int $currentTick): bool {
        // 좀비의 업데이트 로직 (이동, 공격 등)
        $this->getLogger()->info("좀비 업데이트 중: " . $this->getName());
        return parent::onUpdate($currentTick);
    }
}
