<?php

namespace HybridMobAI;

use pocketmine\entity\Living;
use pocketmine\math\Vector3;
use pocketmine\math\VectorMath;
use pocketmine\block\Block;
use pocketmine\block\Stair;
use pocketmine\block\Slab;
use pocketmine\block\Fence;
use pocketmine\block\Wall;
use pocketmine\block\Trapdoor;
use pocketmine\world\World;
use pocketmine\Server;
use pocketmine\scheduler\ClosureTask;

class ObstacleDetector {

    private Main $plugin;
    
    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function checkForObstaclesAndJump(Living $mob, World $world): void {
        $position = $mob->getPosition();
        $yaw = $mob->getLocation()->yaw;

        // 1. Check main forward direction first
        $mainDirection = VectorMath::getDirection2D($yaw);
        $mainVector = new Vector3($mainDirection->x, 0, $mainDirection->y);
        $mainBlockPos = $position->addVector($mainVector);

        $mainBlock = $world->getBlockAt((int)$mainBlockPos->x, (int)$mainBlockPos->y, (int)$mainBlockPos->z);
        $mainBlockAbove = $world->getBlockAt((int)$mainBlockPos->x, (int)$mainBlockPos->y + 1, (int)$mainBlockPos->z);
        $heightDiff = $mainBlock->getPosition()->y + 1 - $position->y;

        $isPathBlocked = true;

        // 2. Handle obstacles in main direction
        if ($heightDiff > 0) {
            if ($this->isSteppable($mainBlock, $mainBlockAbove)) {
                $this->stepUp($mob, $heightDiff);
                $isPathBlocked = false;
            } elseif ($this->isJumpable($mainBlock, $mainBlockAbove, $heightDiff)) {
                $this->jump($mob, $heightDiff);
                $isPathBlocked = false;
            }
        } else {
            // No obstacle in main path
            $isPathBlocked = false;
        }

        // 3. If still blocked, check adjacent paths
        if ($isPathBlocked) {
            $this->checkAdjacentPaths($mob, $world, $yaw);
        }
    }

    private function checkAdjacentPaths(Living $mob, World $world, float $yaw): void {
        $position = $mob->getPosition();
        
        // Check 30 degrees left/right for navigable paths
        $angles = [$yaw + 30, $yaw - 30];
        foreach ($angles as $angle) {
            $dir = VectorMath::getDirection2D($angle);
            $vec = new Vector3($dir->x, 0, $dir->y);
            $checkPos = $position->addVector($vec);

            $block = $world->getBlockAt((int)$checkPos->x, (int)$checkPos->y, (int)$checkPos->z);
            $blockAbove = $world->getBlockAt((int)$checkPos->x, (int)$checkPos->y + 1, (int)$checkPos->z);
            $heightDiff = $block->getPosition()->y + 1 - $position->y;

            if ($heightDiff > 0) {
                if ($this->isSteppable($block, $blockAbove)) {
                    $this->stepUp($mob, $heightDiff);
                    return;
                } elseif ($this->isJumpable($block, $blockAbove, $heightDiff)) {
                    $this->jump($mob, $heightDiff);
                    return;
                }
            } elseif ($block->isTransparent() && $blockAbove->isTransparent()) {
                $this->adjustMovement($mob, $vec);
                return;
            }
        }

        // 4. If no adjacent path, attempt wider detour
        $this->findWiderDetour($mob, $world, $yaw);
    }

    private function findWiderDetour(Living $mob, World $world, float $yaw): void {
        $position = $mob->getPosition();
        
        // Check 90 degrees left/right for open areas
        $angles = [$yaw + 90, $yaw - 90];
        foreach ($angles as $angle) {
            $dir = VectorMath::getDirection2D($angle);
            $vec = new Vector3($dir->x, 0, $dir->y);
            $checkPos = $position->addVector($vec->multiply(2));

            $block = $world->getBlockAt((int)$checkPos->x, (int)$checkPos->y, (int)$checkPos->z);
            $blockAbove = $world->getBlockAt((int)$checkPos->x, (int)$checkPos->y + 1, (int)$checkPos->z);

            if ($block->isTransparent() && $blockAbove->isTransparent()) {
                $this->adjustMovement($mob, $vec);
                return;
            }
        }
    }

    private function adjustMovement(Living $mob, Vector3 $direction): void {
        $mob->setMotion($direction->multiply(0.15)->add(0, $mob->getMotion()->y, 0));
    }

    private function isSteppable(Block $block, Block $blockAbove): bool {
        return ($block instanceof Stair || $block instanceof Slab) && 
               $blockAbove->isTransparent();
    }

    private function isJumpable(Block $block, Block $blockAbove, float $heightDiff): bool {
        return ($block instanceof Fence || 
                $block instanceof Wall || 
                $block instanceof Trapdoor || 
                $block->isSolid()) && 
               $blockAbove->isTransparent() && 
               $heightDiff <= 1.2;
    }

    private function stepUp(Living $mob, float $heightDiff): void {
        $direction = $mob->getDirectionVector()->normalize()->multiply(0.12);
        $mob->setMotion(new Vector3(
            $direction->x,
            0.3 + ($heightDiff * 0.1),
            $direction->z
        ));

        $this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($mob): void {
            if ($mob->isOnGround()) {
                $this->checkForObstaclesAndJump($mob, $mob->getWorld());
            }
        }), 2);
    }

    private function jump(Living $mob, float $heightDiff): void {
        $baseJumpForce = 0.42;
        $extraJumpBoost = min(0.1 * $heightDiff, 0.2);
        $jumpForce = $baseJumpForce + $extraJumpBoost;

        if ($mob->isOnGround() || $mob->getMotion()->y <= 0.1) {
            $mob->setMotion(new Vector3(
                $mob->getMotion()->x,
                $jumpForce,
                $mob->getMotion()->z
            ));
        }
    }
}
