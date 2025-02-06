<?php

namespace HybridMobAI;

use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\entity\Living;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use pocketmine\player\Player;
use pocketmine\math\VectorMath;
use pocketmine\block\Block;

class MobAITask extends Task {
    private Main $plugin;
    private int $tickCounter = 0;
    private array $isJumping = [];

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function onRun(): void {
        $this->tickCounter++;

        if ($this->tickCounter % 2 !== 0) {
            return;
        }

        foreach (Server::getInstance()->getWorldManager()->getWorlds() as $world) {
            foreach ($world->getEntities() as $entity) {
                if ($entity instanceof Zombie) { // Zombie 엔티티만 처리
                    $this->handleMobAI($entity);
                }
            }
        }
    }

    private function handleMobAI(Zombie $mob): void {
        $nearestPlayer = $this->findNearestPlayer($mob);
        if ($nearestPlayer !== null) {
            $this->moveToPlayer($mob, $nearestPlayer);
        } else {
            $this->moveRandomly($mob);
        }

        $this->checkForObstaclesAndJump($mob);
    }

    private function findNearestPlayer(Zombie $mob): ?Player {
        $closestDistance = PHP_FLOAT_MAX;
        $nearestPlayer = null;

        foreach ($mob->getWorld()->getPlayers() as $player) {
            $distance = $mob->getPosition()->distance($player->getPosition());
            if ($distance < $closestDistance) {
                $closestDistance = $distance;
                $nearestPlayer = $player;
            }
        }

        return $nearestPlayer;
    }

    private function moveToPlayer(Zombie $mob, Player $player): void {
        $mobPos = $mob->getPosition();
        $playerPos = $player->getPosition();

        $mobVec3 = new Vector3($mobPos->getX(), $mobPos->getY(), $mobPos->getZ());
        $playerVec3 = new Vector3($playerPos->getX(), $playerPos->getY(), $playerPos->getZ());

        $distance = $mobVec3->distance($playerVec3);
        $speed = 0.2;
        if ($distance < 5) {
            $speed *= $distance / 5;
        }

        $motion = $playerVec3->subtractVector($mobVec3)->normalize()->multiply($speed);

        $currentMotion = $mob->getMotion();
        $blendedMotion = new Vector3(
            ($currentMotion->getX() * 0.5) + ($motion->getX() * 0.5),
            $currentMotion->getY(),
            ($currentMotion->getZ() * 0.5) + ($motion->getZ() * 0.5)
        );

        $mob->setMotion($blendedMotion);
        $mob->lookAt($playerPos);
    }

    private function checkForObstaclesAndJump(Living $mob): void {
    $entityId = $mob->getId();

    if (isset($this->isJumping[$entityId]) && $this->isJumping[$entityId]) {
        if ($mob->isOnGround()) {
            $this->isJumping[$entityId] = false;
        }
        return;
    }

    $position = $mob->getPosition();
    $world = $mob->getWorld();

    // ✅ 방향 벡터 계산
    $directionVector = $mob->getDirectionVector()->normalize();

    // ✅ 블록 감지를 위해 위치를 int로 변환 (더 정확한 감지)
    $frontX = (int) ($position->getX() + $directionVector->getX());
    $frontZ = (int) ($position->getZ() + $directionVector->getZ());
    $currentY = (int) $position->getY();

    // ✅ 앞쪽 1칸 및 2칸의 블록 감지
    $blockInFront1 = $world->getBlockAt($frontX, $currentY, $frontZ);
    $blockAboveInFront1 = $world->getBlockAt($frontX, $currentY + 1, $frontZ);
    
    $blockInFront2 = $world->getBlockAt($frontX, $currentY + 1, $frontZ);
    $blockAboveInFront2 = $world->getBlockAt($frontX, $currentY + 2, $frontZ);

    // ✅ 현재 높이와 장애물 높이 비교 (floor 적용)
    $frontHeight1 = (int) $blockInFront1->getPosition()->getY();
    $frontHeight2 = (int) $blockInFront2->getPosition()->getY();

    $heightDiff1 = $frontHeight1 - $currentY;
    $heightDiff2 = $frontHeight2 - $currentY;

    // ✅ 높이 차이가 0.5~1.5 사이이고, 블록이 단단한 경우 점프 실행
    if ($heightDiff1 >= 0.5 && $heightDiff1 <= 1.5 && $blockInFront1->isSolid() && $blockAboveInFront1->isTransparent()) {
        $this->jump($mob, $heightDiff1);
        $this->isJumping[$entityId] = true;
    } elseif ($heightDiff2 >= 0.5 && $heightDiff2 <= 1.5 && $blockInFront2->isSolid() && $blockAboveInFront2->isTransparent()) {
        $this->jump($mob, $heightDiff2);
        $this->isJumping[$entityId] = true;
    }
}

    public function moveRandomly(Living $mob): void {
        $directionVectors = [
            new Vector3(1, 0, 0),
            new Vector3(-1, 0, 0),
            new Vector3(0, 0, 1),
            new Vector3(0, 0, -1)
        ];
        $randomDirection = $directionVectors[array_rand($directionVectors)];

        $currentMotion = $mob->getMotion();
        $blendedMotion = new Vector3(
            ($currentMotion->getX() * 0.8) + ($randomDirection->getX() * 0.2),
            $currentMotion->getY(),
            ($currentMotion->getZ() * 0.8) + ($randomDirection->getZ() * 0.2)
        );

        $mob->setMotion($blendedMotion);
    }

    public function jump(Living $mob, float $heightDiff = 1.0): void {
    $jumpForce = min(0.7 + ($heightDiff * 0.3), 1.2); // ✅ 최대 점프 높이 1.2로 제한
    $mob->setMotion(new Vector3(
        $mob->getMotion()->getX(),
        $jumpForce,
        $mob->getMotion()->getZ()
    ));
}
}
