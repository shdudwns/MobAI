<?php

namespace HybridMobAI;

use pocketmine\entity\Living;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\entity\Location;
use pocketmine\player\Player;

class Zombie extends Living {
    private AIBehavior $aiBehavior;
    private $plugin;
    private int $moveCooldown = 0; // 움직임 간격을 제어하는 변수

    public function __construct(Location $location, CompoundTag $nbt, $plugin) {
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

    public function onUpdate(int $currentTick): bool {
        if ($this->isAlive()) {
            if ($this->moveCooldown <= 0) {
                // AI 행동 수행
                $this->aiBehavior->performAI($this);
                $this->moveCooldown = 20; // 1초 간격으로 움직임 업데이트 (20틱)
            } else {
                $this->moveCooldown--;
            }
        }
        return parent::onUpdate($currentTick);
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
