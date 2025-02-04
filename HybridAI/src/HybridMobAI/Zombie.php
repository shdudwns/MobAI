<?php

namespace HybridMobAI;

use pocketmine\entity\EntitySizeInfo;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\entity\Living;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\World;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\entity\Location;

class Zombie extends Living {
    public const NETWORK_ID = EntityIds::ZOMBIE;

    public function __construct(Location $location, CompoundTag $nbt) {
        parent::__construct($location, $nbt);
        $this->setPosition($location);
    }

    protected function initEntity(CompoundTag $nbt): void {
        parent::initEntity($nbt);
        // 추가 초기화 작업이 필요한 경우 여기에 작성
    }

    public function onUpdate(int $currentTick): bool {
        // 부모 클래스의 onUpdate를 호출하여 기본 동작을 수행
        parent::onUpdate($currentTick);

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
        $direction = new Vector3(
            $player->getLocation()->getX() - $this->location->getX(),
            $player->getLocation()->getY() - $this->location->getY(),
            $player->getLocation()->getZ() - $this->location->getZ()
        );
        $this->lookAt($player->getLocation());
        $this->setMotion($direction->normalize()->multiply(0.1)); // 이동 속도 조정
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
}
