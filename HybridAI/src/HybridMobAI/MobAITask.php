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

    private function checkForObstaclesAndJump(Living $mob): void {
    $position = $mob->getPosition();
    $world = $mob->getWorld();

    $yaw = $mob->getLocation()->getYaw();
    $direction2D = VectorMath::getDirection2D($yaw);
    $directionVector = new Vector3($direction2D->getX(), 0, $direction2D->getY());

    $leftVector = new Vector3(-$directionVector->getZ(), 0, $directionVector->getX());
    $rightVector = new Vector3($directionVector->getZ(), 0, -$directionVector->getX());

    // 좌우 블록 검사 (두 블록 모두 막혀있으면 점프 안함)
    $leftBlockX = (int)floor($position->getX() + $leftVector->getX());
    $leftBlockY = (int)floor($position->getY());
    $leftBlockZ = (int)floor($position->getZ() + $leftVector->getZ());
    $leftBlock = $world->getBlockAt($leftBlockX, $leftBlockY, $leftBlockZ);

    $rightBlockX = (int)floor($position->getX() + $rightVector->getX());
    $rightBlockY = (int)floor($position->getY());
    $rightBlockZ = (int)floor($position->getZ() + $rightVector->getZ());
    $rightBlock = $world->getBlockAt($rightBlockX, $rightBlockY, $rightBlockZ);


    if ($leftBlock->isSolid() && $rightBlock->isSolid()) {
        return;
    }

    // 앞 블록 검사 (대각선 포함)
    for ($i = 0; $i <= 1; $i++) {
        for ($j = -1; $j <= 1; $j++) {
            $frontBlockX = (int)floor($position->getX() + $directionVector->getX() * $i + $leftVector->getX() * $j);
            $frontBlockY = (int)floor($position->getY());
            $frontBlockZ = (int)floor($position->getZ() + $directionVector->getZ() * $i + $leftVector->getZ() * $j);

            $frontBlock = $world->getBlockAt($frontBlockX, $frontBlockY, $frontBlockZ);
            $frontBlockAbove = $world->getBlockAt($frontBlockX, $frontBlockY + 1, $frontBlockZ);
            $frontBlockBelow = $world->getBlockAt($frontBlockX, $frontBlockY - 1, $frontBlockZ);

            $blockHeight = $frontBlock->getPosition()->getY();
            $heightDiff = $blockHeight - $position->getY();

            // 내려가는 상황이거나 앞 블록 아래가 비어있으면 점프 안함
            if ($heightDiff < 0 || $frontBlockBelow->isTransparent()) {
                continue;
            }

            // 점프 조건: 오를 수 있는 블록이고, 위에 공간이 있고, 너무 멀리 있지 않아야 함
            if ($this->isClimbable($frontBlock) && $frontBlockAbove->isTransparent() && $position->distance($frontBlock->getPosition()) <= 1.5) {
                $this->jump($mob, $heightDiff);
                return; // 틱 당 한 번만 점프
            }
        }
    }
}


public function jump(Living $mob, float $heightDiff = 1.0): void {
    // 점프 힘 조절 (필요에 따라 조절)
    $jumpForce = min(0.6 + ($heightDiff * 0.2), 1.0);

    if (!$mob->isOnGround()) {
        return;
    }

    $mob->setMotion(new Vector3(
        $mob->getMotion()->getX(),
        $jumpForce,
        $mob->getMotion()->getZ()
    ));
}



    private function isClimbable(Block $block): bool {
        $climbableBlocks = [
            "pocketmine:block:slab",
            "pocketmine:block:stairs",
            "pocketmine:block:snow_layer",
            "pocketmine:block:fence",
            "pocketmine:block:glass",
            "pocketmine:block:frame"
        ];
        return $block->isSolid() || in_array($block->getName(), $climbableBlocks);
    }
}
