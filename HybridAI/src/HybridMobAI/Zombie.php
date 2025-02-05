<?php

namespace HybridMobAI;

use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\World;
use pocketmine\entity\EntityDataHelper;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;

class Zombie extends Living {
    private Main $plugin;

    public function __construct(Location $location, CompoundTag $nbt, Main $plugin) {
        parent::__construct($location, $nbt);
        $this->plugin = $plugin;
        $this->adjustSpawnLocation();
    }

    protected function initEntity(CompoundTag $nbt): void {
        parent::initEntity($nbt);
    }

    /** ✅ 블록 충돌 방지: 스폰 위치 조정 */
    private function adjustSpawnLocation(): void {
        $world = $this->getWorld();
        $pos = $this->getPosition();
        $block = $world->getBlockAt((int) $pos->x, (int) $pos->y, (int) $pos->z);

        if ($block !== null && !$block->isTransparent()) {
            $this->teleport($pos->add(0, 1, 0));
        }
    }

    public function onUpdate(int $currentTick): bool {
        if (!$this->isAlive()) {
            return false;
        }

        return parent::onUpdate($currentTick);
    }

    public function getName(): string {
        return "Zombie";
    }

    protected function getInitialSizeInfo(): EntitySizeInfo {
        return new EntitySizeInfo(1.95, 0.6);
    }

    /** ✅ 반환 타입을 `string`으로 변경 */
    public static function getNetworkTypeId(): string {
        return "minecraft:zombie";
    }
}
