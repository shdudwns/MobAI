<?php

namespace HybridMobAI;

use pocketmine\entity\Creature;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\math\Vector3;

class Zombie extends Creature {
    private $aiBehavior;
    private $plugin;

    public function __construct($location, $nbt, $plugin) {
        parent::__construct($location, $nbt);
        $this->plugin = $plugin;
        $this->aiBehavior = new AIBehavior($plugin);
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

    public function onTick(int $currentTick): bool {
        if ($this->isAlive()) {
            // AI 행동 수행
            $this->aiBehavior->moveRandomly($this);
        }
        return parent::onTick($currentTick);
    }

    // 추가적인 행동 메서드를 호출할 수 있습니다.
    public function moveToPlayer($player): void {
        $this->aiBehavior->moveToPlayer($this, $player);
    }

    public function attackPlayer($player): void {
        $this->aiBehavior->attackPlayer($this, $player);
    }

    public function retreat(): void {
        $this->aiBehavior->retreat($this);
    }

    public function jump(): void {
        $this->aiBehavior->jump($this);
    }
}
