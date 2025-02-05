<?php

namespace HybridMobAI;

use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\EntityIds;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\plugin\PluginBase;
use pocketmine\world\World;
use pocketmine\scheduler\TaskScheduler;

class Zombie extends Living {
    private Main $plugin;
    private MobAITask $aiTask;

    public function __construct(World $world, CompoundTag $nbt, Main $plugin) {
        $location = Location::fromObject($world->getSpawnLocation(), $world);
        parent::__construct($location, $nbt);
        $this->plugin = $plugin;
        $this->scheduleAITask($plugin->getScheduler());
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

    private function scheduleAITask(TaskScheduler $scheduler): void {
        $this->aiTask = new MobAITask($this->plugin);
        $scheduler->scheduleRepeatingTask($this->aiTask, 20);
    }

    public function getName(): string {
        return "Zombie";
    }

    protected function getInitialSizeInfo(): EntitySizeInfo {
        return new EntitySizeInfo(1.95, 0.6);
    }

    public static function getNetworkTypeId(): string {
        return EntityIds::ZOMBIE;
    }
}
