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
    private array $isJumping = []; // ✅ 점프 상태 저장

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
                if ($entity instanceof Zombie) {
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

        // ✅ 기존 속도와 새로운 속도를 부드럽게 혼합 (50:50)
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
    $yaw = $mob->getLocation()->getYaw();
    $direction2D = VectorMath::getDirection2D($yaw);
    $directionVector = new Vector3($direction2D->getX(), 0, $direction2D->getY());

    // ✅ 블록 한 개만 감지 (앞쪽 1칸만 검사)
    $nextPosition = new Vector3(
        $position->getX() + $directionVector->getX(),
        $position->getY(),
        $position->getZ() + $directionVector->getZ()
    );

    $blockInFront = $world->getBlockAt((int) $nextPosition->getX(), (int) $nextPosition->getY(), (int) $nextPosition->getZ());
    $blockAboveInFront = $world->getBlockAt((int) $nextPosition->getX(), (int) $nextPosition->getY() + 1, (int) $nextPosition->getZ());
    $blockAbove2InFront = $world->getBlockAt((int) $nextPosition->getX(), (int) $nextPosition->getY() + 2, (int) $nextPosition->getZ());

    // ✅ 현재 발밑 블록 높이 가져오기
    $blockBelow = $world->getBlockAt((int) $position->getX(), (int) $position->getY() - 1, (int) $position->getZ());
    $currentHeight = $position->getY();
    $frontHeight = $blockInFront->getPosition()->getY();

    // ✅ 높이 차이가 0.1 이하이면 점프하지 않음 (대각선 길 방지)
    if (abs($frontHeight - $currentHeight) <= 0.1) {
        return;
    }

    // ✅ 높이 차이가 0.6~1.2 사이일 때만 점프 (대각선 점프 방지)
    $heightDiff = $frontHeight - $currentHeight;
    if ($blockInFront->isSolid() && $blockAboveInFront->isTransparent() && $blockAbove2InFront->isTransparent() && $heightDiff >= 0.6 && $heightDiff <= 1.2) {
        $this->jump($mob, $heightDiff);
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
    $jumpForce = min(0.7 + ($heightDiff * 0.3), 1.1); // ✅ 최대 점프 높이 1.1로 제한
    $mob->setMotion(new Vector3($mob->getMotion()->getX(), $jumpForce, $mob->getMotion()->getZ()));
}
}
