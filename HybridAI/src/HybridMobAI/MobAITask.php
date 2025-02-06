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

    // 대각선 이동 감지 제거

    // 좌우 블록 감지 로직 개선
    $leftVector = new Vector3(-$directionVector->getZ(), 0, $directionVector->getX());
    $rightVector = new Vector3($directionVector->getZ(), 0, -$directionVector->getX());

    $leftBlock = $world->getBlockAt($position->add($leftVector)->floor());
    $rightBlock = $world->getBlockAt($position->add($rightVector)->floor());

    if ($leftBlock->isSolid() && $rightBlock->isSolid()) {
        return;
    }

    // 앞 블록 감지 로직 개선 (높이차 고려, 1칸만 확인)
    $frontBlockPos = $position->add($directionVector)->floor();
    $frontBlock = $world->getBlockAt($frontBlockPos);
    $frontBlockAbove = $world->getBlockAt($frontBlockPos->add(0, 1, 0));

    $currentHeight = (int)floor($position->getY());
    $blockHeight = (int)floor($frontBlock->getPosition()->getY());
    $heightDiff = $blockHeight - $currentHeight;

    // 내려가는 상황 감지 및 점프 방지
    if ($heightDiff < 0) {
        return;
    }

    if ($this->isClimbable($frontBlock) && $frontBlockAbove->isTransparent()) {
        $this->jump($mob, $heightDiff);
        $this->isJumping[$entityId] = true;
        return;
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
