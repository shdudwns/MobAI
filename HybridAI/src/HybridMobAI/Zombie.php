<?php

namespace HybridMobAI;

use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\plugin\PluginBase;
use pocketmine\world\World;
use pocketmine\scheduler\TaskScheduler;
use pocketmine\entity\EntityDataHelper;

class Zombie extends Living {
    private Main $plugin;
    private MobAITask $aiTask;

    public function __construct(Location $location, CompoundTag $nbt, Main $plugin) {
        parent::__construct($location, $nbt);
        $this->plugin = $plugin; // $plugin 속성 초기화
        $this->scheduleAITask($this->plugin->getScheduler());
        $this->adjustSpawnLocation();
    }

    protected function initEntity(CompoundTag $nbt): void {
        parent::initEntity($nbt);
        // 추가적인 초기화 코드가 필요하다면 여기에 작성
    }

    /** ✅ 블록 충돌 방지: 스폰 위치 조정 */
    private function adjustSpawnLocation(): void {
        $world = $this->getWorld();
        $pos = $this->getPosition();
        $block = $world->getBlockAt((int) $pos->x, (int) $pos->y, (int) $pos->z);

        // ✅ `null` 체크 후 `isTransparent()` 호출
        if ($block !== null && !$block->isTransparent()) {
            $this->teleport($pos->add(0, 1, 0)); // 한 블록 위로 이동
        }
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
        return "minecraft:zombie"; // 네트워크 ID를 직접 정의
    }
}
