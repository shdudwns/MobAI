<?php

namespace HybridMobAI;

use pocketmine\entity\Living;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

class EntityNavigator {
    public function moveToPlayer(Living $mob, Player $player): void {
        $mobPos = $mob->getPosition();
        $playerPos = $player->getPosition();

        $speed = 0.2; // 속도를 일정하게 유지

        $motion = $playerPos->subtractVector($mobPos)->normalize()->multiply($speed);
        $currentMotion = $mob->getMotion();

        $inertiaFactor = 0.2; // 부드러운 이동 적용
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
}
