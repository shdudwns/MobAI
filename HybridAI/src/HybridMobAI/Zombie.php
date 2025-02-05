<?php

namespace HybridMobAI;

use pocketmine\entity\Monster;
use pocketmine\entity\Location;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\plugin\PluginBase;

class Zombie extends Monster {
    private Main $plugin;
    private MobAITask $aiTask;

    public function __construct(Location $location, CompoundTag $nbt, Main $plugin) {
        parent::__construct($location, $nbt);
        $this->plugin = $plugin;
        $this->aiTask = new MobAITask($plugin);
        $this->plugin->getScheduler()->scheduleRepeatingTask($this->aiTask, 20);
    }

    protected function initEntity(CompoundTag $nbt): void {
        parent::initEntity($nbt);
        // 추가적인 초기화 코드가 필요하다면 여기에 작성
    }

    public function onUpdate(int $currentTick): bool {
        if (!$this->isAlive()) {
            return false;
        }

        // AI 동작 수행
        $this->aiTask->handleMobAI($this);

        return parent::onUpdate($currentTick);
    }
}
