<?php

namespace HybridMobAI;

use pocketmine\entity\Living;
use pocketmine\entity\Entity;
use pocketmine\entity\Location;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\entity\EntitySizeInfo;

class Zombie extends Living {

    /** ✅ `static` 키워드 유지 **/
    public static function getNetworkTypeId(): string {
        return "minecraft:zombie"; // 좀비의 네트워크 ID
    }

    protected function getInitialSizeInfo(): EntitySizeInfo {
        return new EntitySizeInfo(1.95, 0.6); // 좀비의 크기
    }

    public function __construct(Location $location, ?CompoundTag $nbt = null) {
        parent::__construct($location, $nbt);
    }

    public function onUpdate(int $currentTick): bool {
        if ($this->isClosed() || !$this->isAlive()) {
            return false;
        }

        // 가장 가까운 플레이어 찾기
        $nearestPlayer = $this->findNearestPlayer();
        if ($nearestPlayer !== null) {
            $this->moveTowards($nearestPlayer->getPosition());
        }

        return parent::onUpdate($currentTick);
    }

    private function findNearestPlayer(): ?Player {
        $nearest = null;
        $nearestDistance = PHP_FLOAT_MAX;

        foreach ($this->getWorld()->getPlayers() as $player) {
            $distance = $this->getPosition()->distanceSquared($player->getPosition());
            if ($distance < $nearestDistance) {
                $nearestDistance = $distance;
                $nearest = $player;
            }
        }

        return $nearest;
    }

    private function moveTowards(Vector3 $target): void {
        $direction = $target->subtract($this->getPosition())->normalize()->multiply(0.15); // 이동 속도 조정
        $this->setMotion($direction);
    }
}
