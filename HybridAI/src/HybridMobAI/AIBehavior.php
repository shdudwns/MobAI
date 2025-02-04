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

        // 장애물 앞에서 점프하도록 추가
        $this->checkForObstaclesAndJump($mob);
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
        $directionVectors = [
            new Vector3(1, 0, 0),
            new Vector3(-1, 0, 0),
            new Vector3(0, 0, 1),
            new Vector3(0, 0, -1)
        ];
        $randomDirection = $directionVectors[array_rand($directionVectors)];
        $motion = $randomDirection->multiply(0.1); // 이동 속도 증가
        $mob->setMotion($motion);
        $newPosition = $mob->getPosition()->add($motion->getX(), $motion->getY(), $motion->getZ());
        $mob->lookAt($newPosition);
    }

    public function moveToPlayer(Living $mob, Player $player): void {
        $mobPosition = $mob->getPosition();
        $playerPosition = $player->getPosition();

        $direction = new Vector3(
            $playerPosition->getX() - $mobPosition->getX(),
            $playerPosition->getY() - $mobPosition->getY(),
            $playerPosition->getZ() - $mobPosition->getZ()
        );

        $motion = $direction->normalize()->multiply(0.15); // 이동 속도 증가
        $mob->setMotion($motion);
        $mob->lookAt($player->getPosition());
    }

    private function checkForObstaclesAndJump(Living $mob): void {
        $position = $mob->getPosition();
        $world = $mob->getWorld();
        $frontPosition = $position->add($mob->getDirectionVector()->multiply(1)); // 앞쪽 블록 위치

        // 앞쪽 블록이 장애물이면 점프
        $block = $world->getBlock($frontPosition);
        if (!$block->isTransparent() && $block->getPosition()->getY() <= $position->getY()) {
            $mob->jump();
        }
    }

    public function attackPlayer(Living $mob, Player $player): void {
        // 플레이어를 공격하는 로직
    }

    public function retreat(Living $mob): void {
        // 후퇴하는 로직
    }

    public function jump(Living $mob): void {
        $jumpForce = 0.5;
        $mob->setMotion(new Vector3($mob->getMotion()->getX(), $jumpForce, $mob->getMotion()->getZ()));
    }
}
