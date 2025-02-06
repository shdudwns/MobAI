<?php

namespace HybridMobAI;

use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\entity\Living;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\math\VectorMath;
use pocketmine\block\Block;

class MobAITask extends Task {
    private Main $plugin;
    private int $tickCounter = 0;
    private array $hasLanded = [];

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
        "pocketmine:block:snow_layer",
        "pocketmine:block:fence",
        "pocketmine:block:glass",
        "pocketmine:block:frame"
    ];
    return $block->isSolid() || in_array($block->getName(), $climbableBlocks);
}

private function checkForObstaclesAndJump(Living $mob): void {
    $position = $mob->getPosition();
    $world = $mob->getWorld();

    $yaw = $mob->getLocation()->getYaw();
    $direction2D = VectorMath::getDirection2D($yaw);
    $directionVector = new Vector3($direction2D->getX(), 0, $direction2D->getY());

    $leftVector = new Vector3(-$directionVector->getZ(), 0, $directionVector->getX());
    $rightVector = new Vector3($directionVector->getZ(), 0, -$directionVector->getX());

    // Check left and right blocks at feet level
    $leftBlock = $world->getBlockAt((int)floor($position->x + $leftVector->x), (int)$position->y, (int)floor($position->z + $leftVector->z));
    $rightBlock = $world->getBlockAt((int)floor($position->x + $rightVector->x), (int)$position->y, (int)floor($position->z + $rightVector->z));

    if ($leftBlock->isSolid() && $rightBlock->isSolid()) {
        return;
    }

    // Check front blocks
    for ($i = 0; $i <= 1; $i++) {
        for ($j = -1; $j <= 1; $j++) {
            $frontBlockX = (int)floor($position->x + $directionVector->x * $i + $leftVector->x * $j);
            $frontBlockY = (int)$position->y;
            $frontBlockZ = (int)floor($position->z + $directionVector->z * $i + $leftVector->z * $j);

            $frontBlock = $world->getBlockAt($frontBlockX, $frontBlockY, $frontBlockZ);
            $frontBlockAbove = $world->getBlockAt($frontBlockX, $frontBlockY + 1, $frontBlockZ);
            $frontBlockBelow = $world->getBlockAt($frontBlockX, $frontBlockY - 1, $frontBlockZ);

            $blockHeight = $frontBlock->getPosition()->y;
            $heightDiff = $blockHeight - $position->y;

            if ($heightDiff < 0 || $frontBlockBelow->isTransparent()) {
                continue;
            }

            // Calculate distance to block's center
            $blockCenterX = $frontBlockX + 0.5;
            $blockCenterZ = $frontBlockZ + 0.5;
            $dx = $blockCenterX - $position->x;
            $dz = $blockCenterZ - $position->z;
            $distance = sqrt($dx * $dx + $dz * $dz);

            if ($this->isClimbable($frontBlock) && $frontBlockAbove->isTransparent() && $distance <= 1.0) {
                $this->jump($mob, $heightDiff);
                return;
            }
        }
    }
}


// 개선된 jump() 메서드
public function jump(Living $mob, float $heightDiff = 1.0): void {
    $baseForce = 0.42; // 1블록 점프 기본값
    $jumpForce = $baseForce + ($heightDiff * 0.1);
    $jumpForce = min($jumpForce, 0.8);

    if (!$mob->isOnGround() || $mob->getMotion()->y > 0) {
        return; // 공중에 떠있거나 이미 점프 중이면 취소
    }

    $mob->setMotion(new Vector3(
        $mob->getMotion()->x,
        $jumpForce,
        $mob->getMotion()->z
    ));
}
}
