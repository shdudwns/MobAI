<?php

namespace HybridMobAI;

use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Attribute;
use pocketmine\entity\AttributeFactory;
use pocketmine\event\entity\EntityDamageEvent;

class Zombie extends Living {

    /** ✅ 좀비의 네트워크 ID 설정 (static 유지) **/
    public static function getNetworkTypeId(): string {
        return "minecraft:zombie";
    }

    /** ✅ 엔티티 크기 설정 **/
    protected function getInitialSizeInfo(): EntitySizeInfo {
        return new EntitySizeInfo(1.95, 0.6); // 좀비 크기 (높이, 너비)
    }

    /** ✅ PocketMine-MP 5.x에서는 `getName()` 메서드가 필수 **/
    public function getName(): string {
        return "Custom Zombie"; // 원하는 이름 설정
    }

    public function __construct(Location $location, ?CompoundTag $nbt = null) {
        parent::__construct($location, $nbt);
    }

    /** ✅ 좀비의 기본 체력 설정 **/
    protected function initEntity(CompoundTag $nbt): void {
        parent::initEntity($nbt);
        $this->setMaxHealth(20);
        $this->setHealth(20);
    }

    /** ✅ 좀비가 자동으로 움직이도록 설정 **/
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

    /** ✅ 가장 가까운 플레이어 찾기 **/
    private function findNearestPlayer(): ?Player {
        $nearest = null;
        $nearestDistance = PHP_FLOAT_MAX;

        foreach ($this->getWorld()->getPlayers() as $player) {
            $playerPos = $player->getPosition();

            if (!($playerPos instanceof Vector3)) {
                continue; // 위치 데이터가 없으면 건너뜀
            }

            $distance = $this->getPosition()->distanceSquared(new Vector3(
                (float) $playerPos->x, 
                (float) $playerPos->y, 
                (float) $playerPos->z
            ));

            if ($distance < $nearestDistance) {
                $nearestDistance = $distance;
                $nearest = $player;
            }
        }

        return $nearest;
    }

    /** ✅ 목표 위치로 이동하는 로직 **/
    private function moveTowards(Vector3 $target): void {
        $currentPos = $this->getPosition();

        // 현재 위치와 목표 위치의 차이 계산
        $dx = (float) ($target->x - $currentPos->x);
        $dy = (float) ($target->y - $currentPos->y);
        $dz = (float) ($target->z - $currentPos->z);

        // 벡터 정규화 후 속도 조정
        $length = sqrt($dx * $dx + $dy * $dy + $dz * $dz);
        if ($length > 0) {
            $dx /= $length;
            $dy /= $length;
            $dz /= $length;
        }

        // 이동 속도 적용
        $speed = 0.15;
        $motion = new Vector3($dx * $speed, $dy * $speed, $dz * $speed);
        $this->setMotion($motion);
    }

    /** ✅ 좀비가 피해를 입을 때 처리 (예: 소리 재생) **/
    public function attack(EntityDamageEvent $source): void {
        parent::attack($source);
        $this->broadcastSound("mob.zombie.hurt"); // 좀비 피격 소리 추가
    }

    /** ✅ 좀비가 사망할 때 처리 **/
    public function onDeath(): void {
        parent::onDeath();
        $this->broadcastSound("mob.zombie.death"); // 좀비 사망 소리 추가
    }

    /** ✅ 속성(Attribute) 설정 (이동 속도, 체력 등) **/
    protected function getInitialDragMultiplier(): float {
        return 0.98; // 기본 값 유지
    }

    protected function getInitialGravity(): float {
        return 0.08; // 기본 중력 설정
    }

    protected function getInitialAttributes(): AttributeFactory {
        return AttributeFactory::getInstance()
            ->add(Attribute::getAttribute(Attribute::MOVEMENT_SPEED)->setDefaultValue(0.23)); // 좀비 이동 속도
    }
}
