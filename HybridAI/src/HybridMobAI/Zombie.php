<?php

namespace HybridMobAI;

use pocketmine\entity\Living;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\entity\Location;
use pocketmine\player\Player;
use pocketmine\world\World;
use pocketmine\math\Vector3;

class Zombie extends Living {
    private AIBehavior $aiBehavior;
    private $plugin;

    public function __construct(Location $location, CompoundTag $nbt, $plugin) {
        parent::__construct($location, $nbt);
        $this->plugin = $plugin;
        $this->aiBehavior = new AIBehavior($plugin);

        // 블록 사이에서 스폰될 때 위치를 조정
        $this->adjustSpawnLocation();
    }

    public function getName(): string {
        return "Zombie";
    }

    protected function getInitialSizeInfo(): EntitySizeInfo {
        return new EntitySizeInfo(1.95, 0.6); // 좀비의 높이와 너비 설정
    }

    public static function getNetworkTypeId(): string {
        return EntityIds::ZOMBIE; // 좀비의 네트워크 ID 반환
    }

    public function onUpdate(int $currentTick): bool {
        if ($this->isAlive()) {
            // AI 행동 수행
            $this->aiBehavior->performAI($this);
        }
        return parent::onUpdate($currentTick);
    }

    private function adjustSpawnLocation(): void {
        $world = $this->getWorld();
        $pos = $this->getPosition();
        $block = $world->getBlock($pos);

        // 블록이 투명하지 않다면 위치를 조정
        if (!$block->isTransparent()) {
            $newPosition = $pos->add(0, 1, 0); // 한 블록 위로 이동
            $this->teleport($newPosition);
        }
    }

    // 추가적인 행동 메서드를 호출할 수 있습니다.
    public function moveToPlayer(Player $player): void {
        $this->aiBehavior->moveToPlayer($this, $player);
    }

    public function attackPlayer(Player $player): void {
        $this->aiBehavior->attackPlayer($this, $player);
    }

    public function retreat(): void {
        $this->aiBehavior->retreat($this);
    }

    public function jump(): void {
        $this->aiBehavior->jump($this);
    }
}
