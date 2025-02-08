<?php

namespace HybridMobAI;

use pocketmine\entity\Living;
use pocketmine\math\Vector3;
use pocketmine\math\VectorMath;
use pocketmine\block\Block;
use pocketmine\block\Stair;
use pocketmine\block\Slab;
use pocketmine\world\World;

class ObstacleDetector {
    public function checkForObstaclesAndJump(Living $mob, World $world): void {
        $position = $mob->getPosition();
        $yaw = $mob->getLocation()->yaw;
        $angles = [$yaw, $yaw + 30, $yaw - 30];

        foreach ($angles as $angle) {
            $direction2D = VectorMath::getDirection2D($angle);
            $directionVector = new Vector3($direction2D->x, 0, $direction2D->y);

            $frontBlockPos = $position->addVector($directionVector);
            $frontBlock = $world->getBlockAt((int)$frontBlockPos->x, (int)$frontBlockPos->y, (int)$frontBlockPos->z);
            $frontBlockAbove = $world->getBlockAt((int)$frontBlockPos->x, (int)$frontBlockPos->y + 1, (int)$frontBlockPos->z);
            
            $heightDiff = $frontBlock->getPosition()->y + 1 - $position->y;

            if ($heightDiff < 0.3) continue;

            if ($this->isStairOrSlab($frontBlock) && $frontBlockAbove->isTransparent()) {
                $this->stepUp($mob, $heightDiff);
                return;
            }

            if ($frontBlock->isSolid() && $frontBlockAbove->isTransparent()) {
                if ($heightDiff <= 1.5) {
                    $this->jump($mob, $heightDiff);
                    return;
                }
            }
        }
    }

    private function stepUp(Living $mob, float $heightDiff): void {
        $direction = $mob->getDirectionVector()->normalize()->multiply(0.15);
        $mob->setMotion(new Vector3(
            $direction->x,
            0.2 + ($heightDiff * 0.1),
            $direction->z
        ));
    }

    private function jump(Living $mob, float $heightDiff): void {
        $baseJumpForce = 0.42;
        $extraJumpBoost = min(0.1 * $heightDiff, 0.3);
        $jumpForce = $baseJumpForce + $extraJumpBoost;

        if ($mob->isOnGround() || $mob->getMotion()->y <= 0.1) {
            $mob->setMotion(new Vector3(
                $mob->getMotion()->x,
                $jumpForce,
                $mob->getMotion()->z
            ));
        }
    }

    private function isStairOrSlab(Block $block): bool {
        return $block instanceof Stair || $block instanceof Slab;
    }
}
