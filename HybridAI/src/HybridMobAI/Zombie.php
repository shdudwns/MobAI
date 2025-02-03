<?php

namespace HybridMobAI;

use pocketmine\entity\Entity;
use pocketmine\entity\Living;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\World;
use pocketmine\nbt\tag\CompoundTag;

class Zombie extends Living {
    public const NETWORK_ID = self::ZOMBIE;

    protected function initEntity(CompoundTag $nbt): void {
        parent::initEntity($nbt);
        // 추가 초기화 작업이 필요한 경우 여기에 작성
    }

    public function onUpdate(int $currentTick): bool {
        if(parent::onUpdate($currentTick)) {
            return true;
        }

        // 가까운 플레이어를 찾아 따라가도록 구현
        $nearestPlayer = $this->findNearestPlayer();
        if($nearestPlayer !== null) {
            $this->followPlayer($nearestPlayer);
        }
        
        return true;
    }

    private function findNearestPlayer(): ?Player {
        $nearestPlayer = null;
        $nearestDistance = PHP_INT_MAX;

        foreach($this->getWorld()->getPlayers() as $player) {
            $distance = $this->location->distance($player->getLocation());
            if($distance < $nearestDistance) {
                $nearestDistance = $distance;
                $nearestPlayer = $player;
            }
        }

        return $nearestPlayer;
    }

    private function followPlayer(Player $player): void {
        $direction = $player->getLocation()->subtract($this->location)->normalize();
        $this->setMotion($direction->multiply(0.1)); // 이동 속도 조정
    }

    public function getName(): string {
        return "Zombie";
    }
}
