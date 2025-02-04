<?php

namespace HybridMobAI;

use pocketmine\entity\Living;
use pocketmine\player\Player;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use pocketmine\block\Block;

class AIBehavior {
    private $plugin;

    public function __construct($plugin) {
        $this->plugin = $plugin;
    }

    public function performAI(Living $mob): void {
        $nearestPlayer = $this->findNearestPlayer($mob);
        if ($nearestPlayer !== null) {
            $this->moveToPlayer($mob, $nearestPlayer);
        } else {
            $this->moveRandomly($mob);
        }

        // ✅ 장애물 앞에서만 점프하도록 조건 추가
        $this->checkForObstaclesAndJump($mob);
    }

    /** ✅ 성능 최적화: `distanceSquared()` 사용 */
    private function findNearestPlayer(Living $mob): ?Player {
        $closestDistance = PHP_FLOAT_MAX; // ✅ `PHP_INT_MAX` 대신 `PHP_FLOAT_MAX` 사용
        $nearestPlayer = null;

        foreach ($mob->getWorld()->getPlayers() as $player) {
            $distance = $mob->getPosition()->distanceSquared($player->getPosition()); // ✅ 성능 최적화
            if ($distance < $closestDistance) {
                $closestDistance = $distance;
                $nearestPlayer = $player;
            }
        }

        return $nearestPlayer;
    }

    /** ✅ `lookAt()` 순서 변경 */
    public function moveRandomly(Living $mob): void {
        $directionVectors = [
            new Vector3(1, 0, 0),
            new Vector3(-1, 0, 0),
            new Vector3(0, 0, 1),
            new Vector3(0, 0, -1)
        ];
        $randomDirection = $directionVectors[array_rand($directionVectors)];
        $motion = $randomDirection->multiply(0.1); // ✅ 이동 속도 증가
        $mob->setMotion($motion);

        // ✅ `setMotion()` 후 `lookAt()` 호출
        $newPosition = $mob->getPosition()->add($motion->getX(), $motion->getY(), $motion->getZ());
        $mob->lookAt($newPosition);
    }

    /** ✅ 이동 속도 증가 */
    public function moveToPlayer(Living $mob, Player $player): void {
        $mobPosition = $mob->getPosition();
        $playerPosition = $player->getPosition();

        $direction = new Vector3(
            $playerPosition->getX() - $mobPosition->getX(),
            0, // ✅ Y축 이동 방지
            $playerPosition->getZ() - $mobPosition->getZ()
        );

        $motion = $direction->normalize()->multiply(0.15); // ✅ 이동 속도 증가
        $mob->setMotion($motion);
        $mob->lookAt($playerPosition);
    }

    /** ✅ `isSolid()` 체크 추가 */
    private function checkForObstaclesAndJump(Living $mob): void {
        $position = $mob->getPosition();
        $world = $mob->getWorld();
        $directionVector = $mob->getDirectionVector();
        $frontPosition = $position->add($directionVector->getX(), 0, $directionVector->getZ());

        // ✅ 앞쪽 블록이 `isSolid()`인지 확인
        $blockInFront = $world->getBlockAt((int) $frontPosition->x, (int) $frontPosition->y, (int) $frontPosition->z);
        $blockAboveInFront = $world->getBlockAt((int) $frontPosition->x, (int) $frontPosition->y + 1, (int) $frontPosition->z);

        if ($blockInFront->isSolid() && !$blockAboveInFront->isSolid()) {
            $this->jump($mob);
        }
    }

    public function attackPlayer(Living $mob, Player $player): void {
        // 플레이어를 공격하는 로직
    }

    public function retreat(Living $mob): void {
        // 후퇴하는 로직
    }

    /** ✅ 점프 기능 개선 */
    public function jump(Living $mob): void {
        $jumpForce = 0.6; // ✅ 점프 높이 조절
        $mob->setMotion(new Vector3($mob->getMotion()->getX(), $jumpForce, $mob->getMotion()->getZ()));
    }
}
