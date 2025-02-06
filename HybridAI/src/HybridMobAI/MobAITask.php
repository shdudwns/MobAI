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

    private function isClimbable(Block $block): bool {
    $climbableBlocks = [
        "pocketmine:block:slab",
        "pocketmine:block:stairs",
        "pocketmine:block:snow_layer"
    ];
    return $block->isSolid() || in_array($block->getName(), $climbableBlocks);
}
    
    private function checkForObstaclesAndJump(Living $mob): void {
    $entityId = $mob->getId();

    // ✅ 점프 중이면 착지할 때까지 기다림
    if (isset($this->isJumping[$entityId]) && $this->isJumping[$entityId]) {
        if ($mob->isOnGround()) {
            $this->isJumping[$entityId] = false; // ✅ 착지하면 점프 가능하도록 초기화
        }
        return;
    }

    $position = $mob->getPosition();
    $world = $mob->getWorld();
    
    // ✅ 이동 방향 계산 (Yaw 값 기반)
    $yaw = $mob->getLocation()->getYaw();
    $direction2D = VectorMath::getDirection2D($yaw);
    $directionVector = new Vector3($direction2D->getX(), 0, $direction2D->getY());

    // ✅ 대각선 이동 감지 (대각선 이동 중이면 점프하지 않음)
    if (abs($directionVector->getX()) === abs($directionVector->getZ())) {
        return;
    }

    // ✅ 좌우 블록 감지 (양쪽에 블록이 있으면 점프 방지)
    $leftVector = new Vector3(-$directionVector->getZ(), 0, $directionVector->getX()); // 왼쪽 방향
    $rightVector = new Vector3($directionVector->getZ(), 0, -$directionVector->getX()); // 오른쪽 방향

    $leftBlock = $world->getBlockAt(
        (int)($position->getX() + $leftVector->getX()), 
        (int)$position->getY(), 
        (int)($position->getZ() + $leftVector->getZ())
    );

    $rightBlock = $world->getBlockAt(
        (int)($position->getX() + $rightVector->getX()), 
        (int)$position->getY(), 
        (int)($position->getZ() + $rightVector->getZ())
    );

    // ✅ 양쪽 모두 블록이 있으면 점프하지 않음
    if ($leftBlock->isSolid() && $rightBlock->isSolid()) {
        return;
    }

    // ✅ 앞으로 이동할 블록 위치 계산 (앞쪽 1~2칸 감지)
    for ($i = 1; $i <= 2; $i++) {
        $frontPosition = $position->add(
            $directionVector->getX() * $i,
            0,
            $directionVector->getZ() * $i
        );

        $blockInFront = $world->getBlockAt((int)$frontPosition->getX(), (int)$frontPosition->getY(), (int)$frontPosition->getZ());
        $blockAboveInFront = $world->getBlockAt((int)$frontPosition->getX(), (int)$frontPosition->getY() + 1, (int)$frontPosition->getZ());
        $blockAbove2InFront = $world->getBlockAt((int)$frontPosition->getX(), (int)$frontPosition->getY() + 2, (int)$frontPosition->getZ());

        // ✅ 현재 높이와 장애물 높이 비교
        $currentHeight = (int)floor($position->getY());
        $blockHeight = (int)floor($blockInFront->getPosition()->getY());
        $heightDiff = $blockHeight - $currentHeight;

        // ✅ 점프 가능한 장애물인지 확인
        if ($this->isClimbable($blockInFront) && $blockAboveInFront->isTransparent() && $blockAbove2InFront->isTransparent()) {
            $this->jump($mob, $heightDiff);
            $this->isJumping[$entityId] = true;
            return;
        }
    }
}
    public function jump(Living $mob, float $heightDiff = 1.0): void {
    // ✅ 점프 높이를 자연스럽게 조정 (최대 1블록 점프)
    $jumpForce = min(0.6 + ($heightDiff * 0.2), 1.0);

    // ✅ 현재 점프 중이면 다시 점프하지 않도록 방지
    if (!$mob->isOnGround()) {
        return;
    }

    $mob->setMotion(new Vector3(
        $mob->getMotion()->getX(),
        $jumpForce,
        $mob->getMotion()->getZ()
    ));
}
}
