<?php

namespace HybridMobAI;

use pocketmine\entity\Living;
use pocketmine\math\Vector3;
use pocketmine\player\Player;

class EntityTracker{
  public function findNearestPlayer(Living $mob): ?Player {
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
}
