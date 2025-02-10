<?php

namespace HybridMobAI;

use pocketmine\entity\Living;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\Server;

class EntityNavigator {

    private bool $enabled;
    
    public function moveToPlayer(Living $mob, Player $player, bool $enabled): void {
    $ai = new EntityAI(Main::getInstance(), $enabled);
    if ($ai->isEnabled()) {
        if ($ai->hasPath($mob)) {
            $ai->moveAlongPath($mob);
            return;
        }

        // ✅ 새로운 경로가 없으면 경로 탐색 시도 후 이동
        $ai->findPathAsync(
            $mob->getWorld(),
            $mob->getPosition(),
            $player->getPosition(),
            "A*",
            function (?array $path) use ($mob, $ai) {
                if (!empty($path)) {
                    $ai->setPath($mob, $path);
                    Server::getInstance()->broadcastMessage("✅ 몬스터 {$mob->getId()} 새로운 경로 탐색 완료!");
                    $ai->moveAlongPath($mob);
                }
            }
        );
    } else {

    // 기본 AI 이동 (직진)
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
