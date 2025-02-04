<?php

namespace HybridMobAI;

use pocketmine\entity\Living;
use pocketmine\player\Player;
use pocketmine\math\Vector3;
use pocketmine\world\World;

class AIBehavior {
    private $plugin;

    public function __construct($plugin) {
        $this->plugin = $plugin;
    }

    public function performAI(Living $mob): void {
        $nearestPlayer = $this->findNearestPlayer($mob);
        if ($nearestPlayer !== null) {
            $this->moveToPlayer($mob, $nearestPlayer);
        } else {
            $this->moveRandomly($mob);
        }
    }

    private function findNearestPlayer(Living $mob): ?Player {
        $closestDistance = PHP_INT_MAX;
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

    public function moveRandomly(Living $mob): void {
        $this->plugin->getLogger()->info("랜덤 이동 중: " . $mob->getName());
        $directionVectors = [
            new Vector3(1, 0, 0),
            new Vector3(-1, 0, 0),
            new Vector3(0, 0, 1),
            new Vector3(0, 0, -1)
        ];
        $randomDirection = $directionVectors[array_rand($directionVectors)];
        $mob->setMotion($randomDirection->multiply(0.1)); // 움직임 속도 조정
        $mob->setRotation(mt_rand(0, 360), mt_rand(-90, 90));
    }

    public function moveToPlayer(Living $mob, Player $player): void {
        $this->plugin->getLogger()->info("플레이어에게 이동 중: " . $mob->getName());
        $direction = $player->getPosition()->subtract($mob->getPosition())->normalize();
        $mob->setMotion($direction->multiply(0.1)); // 움직임 속도 조정
        $mob->lookAt($player->getPosition());
    }

    public function attackPlayer(Living $mob, Player $player): void {
        $this->plugin->getLogger()->info("플레이어 공격 중: " . $mob->getName());
        // 플레이어를 공격하는 로직
    }

    public function retreat(Living $mob): void {
        $this->plugin->getLogger()->info("후퇴 중: " . $mob->getName());
        // 후퇴하는 로직
    }

    public function jump(Living $mob): void {
        $this->plugin->getLogger()->info("점프 중: " . $mob->getName());
        $jumpForce = 0.5;
        $mob->setMotion(new Vector3($mob->getMotion()->getX(), $jumpForce, $mob->getMotion()->getZ()));
    }
}
