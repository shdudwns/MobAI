<?php

namespace HybridMobAI;

use pocketmine\entity\Living;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

class EntityNavigator {
    public function moveToPlayer(Living $mob, Player $player): void {
        $mobPos = $mob->getPosition();
        $playerPos = $player->getPosition();
    
        $speed = 0.25;
        $motion = $playerPos->subtractVector($mobPos)->normalize()->multiply($speed);
        $currentMotion = $mob->getMotion();
    
        $inertiaFactor = 0.6; 

        $blendedMotion = new Vector3(
            ($currentMotion->x * $inertiaFactor) + ($motion->x * (1 - $inertiaFactor)),
            $currentMotion->y,
            ($currentMotion->z * $inertiaFactor) + ($motion->z * (1 - $inertiaFactor))
        );

        $mob->setMotion($blendedMotion);
        $mob->lookAt($playerPos);
    }

    public function moveAlongPath(Living $mob, array $path): void {
        if (empty($path)) return;

        $nextPosition = array_shift($path);
        if ($nextPosition instanceof Vector3) {
            $mob->setMotion($nextPosition->subtractVector($mob->getPosition())->normalize()->multiply(0.2));
            $mob->lookAt($nextPosition);
        }
    }

    public function moveRandomly(Living $mob): void {
        $directionVectors = [
            new Vector3(1, 0, 0), new Vector3(-1, 0, 0),
            new Vector3(0, 0, 1), new Vector3(0, 0, -1)
        ];
        $randomDirection = $directionVectors[array_rand($directionVectors)];

        $currentMotion = $mob->getMotion();
        $blendedMotion = new Vector3(
            ($currentMotion->x * 0.8) + ($randomDirection->x * 0.2),
            $currentMotion->y,
            ($currentMotion->z * 0.8) + ($randomDirection->z * 0.2)
        );

        $mob->setMotion($blendedMotion);
    }
}
