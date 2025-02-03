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
        $this->getLogger()->info("좀비 업데이트 중: " . $this->getName());
        // AI 로직을 이곳에 넣어줍니다.
        $this->plugin->getScheduler()->scheduleRepeatingTask(new MobAITask($this->plugin), 20);
        return parent::onUpdate($currentTick);
    }
}
