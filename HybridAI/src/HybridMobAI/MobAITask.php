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

    private function checkForObstaclesAndJump(Living $mob): void {
    $entityId = $mob->getId();

    // ✅ 점프 중이면 착지할 때까지 기다림
    if (isset($this->isJumping[$entityId]) && $this->isJumping[$entityId]) {
        if ($mob->isOnGround()) {
            $this->isJumping[$entityId] = false;
        }
        return;
    }

    $position = $mob->getPosition();
    $world = $mob->getWorld();
    
    // ✅ 이동 방향 계산 (Yaw 값 기반)
    $yaw = $mob->getLocation()->getYaw();
    $direction2D = VectorMath::getDirection2D($yaw);
    $directionVector = new Vector3($direction2D->getX(), 0, $direction2D->getY());

    // ✅ 대각선 이동 감지
    $isDiagonalMove = abs($directionVector->getX()) === abs($directionVector->getZ());

    // ✅ 앞으로 이동할 블록 위치 계산 (앞쪽 1칸, 2칸 감지)
    $frontPosition1 = new Vector3(
        floor($position->getX() + $directionVector->getX()), 
        floor($position->getY()), 
        floor($position->getZ() + $directionVector->getZ())
    );
    
    $frontPosition2 = new Vector3(
        floor($position->getX() + ($directionVector->getX() * 2)), 
        floor($position->getY()), 
        floor($position->getZ() + ($directionVector->getZ() * 2))
    );

    // ✅ 앞쪽 블록 감지 (1칸, 2칸)
    $blockInFront1 = $world->getBlockAt($frontPosition1->getX(), $frontPosition1->getY(), $frontPosition1->getZ());
    $blockAboveInFront1 = $world->getBlockAt($frontPosition1->getX(), $frontPosition1->getY() + 1, $frontPosition1->getZ());

    $blockInFront2 = $world->getBlockAt($frontPosition2->getX(), $frontPosition2->getY(), $frontPosition2->getZ());
    $blockAboveInFront2 = $world->getBlockAt($frontPosition2->getX(), $frontPosition2->getY() + 1, $frontPosition2->getZ());

    // ✅ 현재 높이와 장애물 높이 비교
    $currentHeight = floor($position->getY());
    $frontHeight1 = floor($blockInFront1->getPosition()->getY());
    $frontHeight2 = floor($blockInFront2->getPosition()->getY());

    $heightDiff1 = $frontHeight1 - $currentHeight;
    $heightDiff2 = $frontHeight2 - $currentHeight;

    // ✅ 점프 가능 장애물 리스트 (반블록, 계단, 눈)
    $jumpableBlocks = [
        "pocketmine:block:slab",
        "pocketmine:block:stairs",
        "pocketmine:block:snow_layer"
    ];

    // ✅ 점프 조건:
    // (1) 장애물 존재
    // (2) 위쪽 블록이 비어 있음
    // (3) 높이 차이가 0.5~1.5 사이
    if (!$isDiagonalMove && 
        ($blockInFront1->isSolid() || in_array($blockInFront1->getName(), $jumpableBlocks)) 
        && $blockAboveInFront1->isTransparent() 
        && $heightDiff1 >= 0.5 && $heightDiff1 <= 1.5) {
        
        $this->jump($mob, $heightDiff1);
        $this->isJumping[$entityId] = true;
    }
    elseif (!$isDiagonalMove && 
        ($blockInFront2->isSolid() || in_array($blockInFront2->getName(), $jumpableBlocks)) 
        && $blockAboveInFront2->isTransparent() 
        && $heightDiff2 >= 0.5 && $heightDiff2 <= 1.5) {
        
        $this->jump($mob, $heightDiff2);
        $this->isJumping[$entityId] = true;
    }
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
