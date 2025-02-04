<?php

namespace HybridMobAI;

use pocketmine\entity\Living;
use pocketmine\player\Player;
use pocketmine\math\Vector3;

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
        $motion = $randomDirection->multiply(0.1); // 움직임 속도 조정
        $mob->setMotion($motion);
        $mob->lookAt($mob->getPosition()->add($motion));
    }

    public function moveToPlayer(Living $mob, Player $player): void {
        $this->plugin->getLogger()->info("플레이어에게 이동 중: " . $mob->getName());
        $mobPosition = $mob->getPosition();
        $playerPosition = $player->getPosition();

        // 벡터 좌표를 사용하여 방향 계산
        $direction = new Vector3(
            $playerPosition->getX() - $mobPosition->getX(),
            $playerPosition->getY() - $mobPosition->getY(),
            $playerPosition->getZ() - $mobPosition->getZ()
        );
        
        $motion = $direction->normalize()->multiply(0.1); // 움직임 속도 조정
        $mob->setMotion($motion);
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
