<?php

namespace HybridMobAI;

use pocketmine\entity\Creature;
use pocketmine\math\Vector3;

class AIBehavior {

    private $plugin;

    public function __construct($plugin) {
        $this->plugin = $plugin;
    }

    public function moveRandomly(Creature $mob): void {
        $this->plugin->getLogger()->info("랜덤 이동 중: " . $mob->getName());
        $directionVectors = [
            new Vector3(1, 0, 0),
            new Vector3(-1, 0, 0),
            new Vector3(0, 0, 1),
            new Vector3(0, 0, -1)
        ];
        $randomDirection = $directionVectors[array_rand($directionVectors)];
        $mob->move($randomDirection->multiply(0.25 + mt_rand(0, 10) / 100));
        $mob->setRotation(mt_rand(0, 360), mt_rand(-90, 90));
    }

    public function moveToPlayer(Creature $mob, $player): void {
        $this->plugin->getLogger()->info("플레이어에게 이동 중: " . $mob->getName());
        // 플레이어에게 이동하는 로직
    }

    public function attackPlayer(Creature $mob, $player): void {
        $this->plugin->getLogger()->info("플레이어 공격 중: " . $mob->getName());
        // 플레이어를 공격하는 로직
    }

    public function retreat(Creature $mob): void {
        $this->plugin->getLogger()->info("후퇴 중: " . $mob->getName());
        // 후퇴하는 로직
    }

    public function jump(Creature $mob): void {
        $this->plugin->getLogger()->info("점프 중: " . $mob->getName());
        $jumpForce = 0.5;
        $mob->setMotion(new Vector3($mob->getMotion()->getX(), $jumpForce, $mob->getMotion()->getZ()));
    }
}
