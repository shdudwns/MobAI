<?php

namespace HybridMobAI;

use pocketmine\entity\Living;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use pocketmine\block\Block;
use pocketmine\block\Stair;
use pocketmine\block\Slab;

class ObstacleDetector {
    public function checkForObstaclesAndJump(Living $mob, World $world): void {
        $position = $mob->getPosition();
        $yaw = $mob->getLocation()->yaw;
        $angles = [$yaw, $yaw + 30, $yaw - 30]; // 정밀한 장애물 감지
        foreach ($angles as $angle) {
        $direction2D = VectorMath::getDirection2D($angle);
        $directionVector = new Vector3($direction2D->x, 0, $direction2D->y);

        $frontBlockPos = $position->addVector($directionVector);
        $frontBlock = $world->getBlockAt((int)$frontBlockPos->x, (int)$frontBlockPos->y, (int)$frontBlockPos->z);
        $frontBlockAbove = $world->getBlockAt((int)$frontBlockPos->x, (int)$frontBlockPos->y + 1, (int)$frontBlockPos->z);
        
        $heightDiff = $frontBlock->getPosition()->y + 1 - $position->y;

        if ($heightDiff < 0.3) return;

        if ($frontBlock instanceof Stair || $frontBlock instanceof Slab) {
            $this->stepUp($mob, $heightDiff);
        } else if ($heightDiff <= 1.5) {
            $this->jump($mob, $heightDiff);
        }
        }
    }

    private function stepUp(Living $mob, float $heightDiff): void {
        $mob->setMotion(new Vector3($mob->getMotion()->x, 0.5, $mob->getMotion()->z));
    }

    private function jump(Living $mob, float $heightDiff): void {
        $mob->setMotion(new Vector3($mob->getMotion()->x, 0.7, $mob->getMotion()->z));
    }
}
