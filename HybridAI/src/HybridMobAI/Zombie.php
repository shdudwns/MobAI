<?php

namespace HybridMobAI;

use pocketmine\entity\Living;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\entity\Location;
use pocketmine\player\Player;
use pocketmine\world\World;
use pocketmine\entity\EntityDataHelper;

class Zombie extends Living {
    private AIBehavior $aiBehavior;
    private $plugin;

    public function __construct(World $world, CompoundTag $nbt, $plugin) {
        parent::__construct(EntityDataHelper::parseLocation($nbt, $world), $nbt);
        $this->plugin = $plugin;
        $this->aiBehavior = new AIBehavior($plugin);
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

    public function onUpdate(int $currentTick): bool {
        if ($this->isClosed() || !$this->isAlive()) {
            return false;
        }

        // ✅ AI 실행 (경로 탐색 포함)
        $this->aiBehavior->performAI($this);

        return parent::onUpdate($currentTick);
    }
}
