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
    $entityId = $mob->getId();

    $yaw = $mob->getLocation()->getYaw();
    $direction2D = VectorMath::getDirection2D($yaw);
    $directionVector = new Vector3($direction2D->getX(), 0, $direction2D->getY());

    $leftVector = new Vector3(-$directionVector->getZ(), 0, $directionVector->getX());
    $rightVector = new Vector3($directionVector->getZ(), 0, -$directionVector->getX());

    // Check for blocks directly to the left and right.  If BOTH are solid, don't jump.
    $leftBlock = $world->getBlockAt($position->add($leftVector));
    $rightBlock = $world->getBlockAt($position->add($rightVector));

    if ($leftBlock->isSolid() && $rightBlock->isSolid()) {
        return;
    }

    // Check blocks in front, including diagonals
    for ($i = 0; $i <= 1; $i++) { // Check 1 block forward, then 2 blocks forward.
        for ($j = -1; $j <= 1; $j++) { // Check left, forward, and right diagonals.
            $frontBlock = $world->getBlockAt($position->add($directionVector->multiply($i)->add($leftVector->multiply($j))));
            $frontBlockAbove = $world->getBlockAt($position->add($directionVector->multiply($i)->add($leftVector->multiply($j))->add(0, 1, 0)));
            $frontBlockBelow = $world->getBlockAt($position->add($directionVector->multiply($i)->add($leftVector->multiply($j))->add(0, -1, 0)));


            $blockHeight = $frontBlock->getPosition()->getY();
            $heightDiff = $blockHeight - $position->getY();

            // Prevent jumping if going down or if there's no ground below the front block
            if ($heightDiff < 0 || $frontBlockBelow->isTransparent()) {
                continue;
            }

            // Check if the block is climbable and there's space above it.  Also, check distance.
            if ($this->isClimbable($frontBlock) && $frontBlockAbove->isTransparent() && $position->distance($frontBlock->getPosition()) <= 1.5) {

                $this->jump($mob, $heightDiff);
                return; // Only jump once per tick
            }
        }
    }
}

public function jump(Living $mob, float $heightDiff = 1.0): void {
    // Increased jump force. Adjust as needed.  0.4 was too low.
    $jumpForce = min(0.6 + ($heightDiff * 0.2), 1.0);  // Start higher, scale with height.

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
