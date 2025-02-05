<?php

namespace HybridMobAI;

use pocketmine\entity\Living;
use pocketmine\player\Player;
use pocketmine\math\Vector3;
use pocketmine\Server;
use HybridMobAI\PathfindingTask;

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

        $this->checkForObstaclesAndJump($mob);
    }

    /** ✅ 가장 가까운 플레이어 찾기 */
    private function findNearestPlayer(Living $mob): ?Player {
        $closestDistance = PHP_FLOAT_MAX;
        $nearestPlayer = null;

        foreach ($mob->getWorld()->getPlayers() as $player) {
            $distance = $mob->getPosition()->distanceSquared($player->getPosition());
            if ($distance < $closestDistance) {
                $closestDistance = $distance;
                $nearestPlayer = $player;
            }
        }

        return $nearestPlayer;
    }

    /** ✅ `PathfindingTask`를 사용하여 플레이어에게 이동 */
    public function moveToPlayer(Living $mob, Player $player): void {
        $start = $mob->getPosition();
        $goal = $player->getPosition();
        $mobId = $mob->getId();

        $task = new PathfindingTask($start, $goal, $mobId, "AStar");
        Server::getInstance()->getAsyncPool()->submitTask($task);
    }

    /** ✅ 장애물 감지 후 점프 */
    private function checkForObstaclesAndJump(Living $mob): void {
        $position = $mob->getPosition();
        $world = $mob->getWorld();
        $directionVector = $mob->getLocation()->getDirectionVector();
        $frontPosition = $position->add($directionVector->getX(), 0, $directionVector->getZ());

        $blockInFront = $world->getBlockAt((int) $frontPosition->x, (int) $frontPosition->y, (int) $frontPosition->z);
        $blockAboveInFront = $world->getBlockAt((int) $frontPosition->x, (int) $frontPosition->y + 1, (int) $frontPosition->z);

        if ($blockInFront !== null && !$blockInFront->isTransparent() && $blockAboveInFront !== null && $blockAboveInFront->isTransparent()) {
            $this->jump($mob);
        }
    }

    public function moveRandomly(Living $mob): void {
        $directionVectors = [
            new Vector3(1, 0, 0),
            new Vector3(-1, 0, 0),
            new Vector3(0, 0, 1),
            new Vector3(0, 0, -1)
        ];
        $randomDirection = $directionVectors[array_rand($directionVectors)];
        $mob->setMotion($randomDirection->multiply(0.15));
    }

    public function jump(Living $mob): void {
        $jumpForce = 0.6;
        $mob->setMotion(new Vector3($mob->getMotion()->getX(), $jumpForce, $mob->getMotion()->getZ()));
    }
}
