<?php

namespace HybridMobAI;

use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\entity\Living;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\math\VectorMath;
use pocketmine\block\Block;
use pocketmine\entity\Zombie;

class MobAITask extends Task {
    private Main $plugin;
    private int $tickCounter = 0;
    private array $activePathfinding = [];
    private array $landedTick = [];
    private array $path = []; // Add this line

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function onRun(): void {
        $this->tickCounter++;

        if ($this->tickCounter % 2 !== 0) return;

        foreach (Server::getInstance()->getWorldManager()->getWorlds() as $world) {
            foreach ($world->getEntities() as $entity) {
                if ($entity instanceof Zombie) {
                    $this->handleMobAI($entity);
                }
            }
        }
    }

    private function handleMobAI(Zombie $mob): void {
        $nearestPlayer = $this->findNearestPlayer($mob);
        if ($nearestPlayer !== null) {
            $mobId = $mob->getId();
            if (!isset($this->activePathfinding[$mobId])) {
                $this->startPathfinding($mob, $nearestPlayer);
            } else {
                // Check if pathfinding is still active. If not, re-pathfind.
                if (!Server::getInstance()->getAsyncPool()->isTaskRunning($this->activePathfinding[$mobId])) {
                    unset($this->activePathfinding[$mobId]); // Clean up.
                    $this->startPathfinding($mob, $nearestPlayer); // Start again.
                }
            }
        } else {
            $this->moveRandomly($mob);
        }

        $this->checkForObstaclesAndJump($mob);
    }

    private function startPathfinding(Zombie $mob, Player $player): void {
        $start = $mob->getPosition();
        $goal = $player->getPosition();
        $mobId = $mob->getId();
        $worldName = $mob->getWorld()->getFolderName();

        $task = new PathfinderTask(
            $start->getX(), $start->getY(), $start->getZ(),
            $goal->getX(), $goal->getY(), $goal->getZ(),
            $mobId, "AStar", $worldName
        );

        $this->activePathfinding[$mobId] = $task->getTaskId(); // Store the task ID.
        $this->plugin->getServer()->getAsyncPool()->submitTask($task);
    }

    public function applyPathResult(int $mobId, ?array $path): void {
        unset($this->activePathfinding[$mobId]); // Remove the pathfinding flag.
        $server = Server::getInstance();
        $mob = null;

        foreach ($server->getWorldManager()->getWorlds() as $world) {
            $mob = $world->getEntity($mobId);
            if ($mob instanceof Zombie && $mob->isAlive()) break; // Check if the mob is still valid.
        }

        if ($mob === null) return;

        if ($path === null || empty($path)) {
            $this->moveRandomly($mob);
            return;
        }

        $this->path[$mobId] = $path; // Store the path.
        $this->moveAlongPath($mob, $mobId); // Call moveAlongPath.
    }

    private function moveAlongPath(Zombie $mob, int $mobId): void {
        if (!isset($this->path[$mobId]) || empty($this->path[$mobId])) {
            return; // No path to follow.
        }

        $path = $this->path[$mobId];
        $nextStep = array_shift($path); // Get the first step and remove it.

        if ($nextStep instanceof Vector3) {
            $mob->lookAt($nextStep);
            $motion = $nextStep->subtractVector($mob->getPosition())->normalize()->multiply(0.2);

            if (!is_nan($motion->getX()) && !is_nan($motion->getY()) && !is_nan($motion->getZ())) {
                $mob->setMotion($motion);
            } else {
                Server::getInstance()->getLogger()->warning("Invalid motion vector (NaN values).");
            }

            if (empty($path)) {
                unset($this->path[$mobId]); // Path finished.
            } else {
                $this->path[$mobId] = $path; // Update the path.
            }
        }
    }


    private function findNearestPlayer(Zombie $mob): ?Player {
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

    private function moveRandomly(Living $mob): void {
        $directionVectors = [
            new Vector3(1, 0, 0),
            new Vector3(-1, 0, 0),
            new Vector3(0, 0, 1),
            new Vector3(0, 0, -1)
        ];
        $randomDirection = $directionVectors[array_rand($directionVectors)];

        $mob->setMotion($randomDirection->multiply(0.15));
    }

    private function checkForObstaclesAndJump(Living $mob): void {
        $position = $mob->getPosition();
        $world = $mob->getWorld();
        $currentTick = Server::getInstance()->getTick();
        $mobId = $mob->getId();

        if (isset($this->landedTick[$mobId]) && ($currentTick - $this->landedTick[$mobId] < 2)) return;

        $yaw = $mob->getLocation()->yaw;
        $direction2D = VectorMath::getDirection2D($yaw);
        $directionVector = new Vector3($direction2D->x, 0, $direction2D->y);

        for ($i = 1; $i <= 2; $i++) {
            $frontPosition = new Vector3(
                $position->getX() + ($directionVector->getX() * $i),
                $position->getY(),
                $position->getZ() + ($directionVector->getZ() * $i)
            );

            $blockInFront = $world->getBlockAt((int)$frontPosition->getX(), (int)$frontPosition->getY(), (int)$frontPosition->getZ());
            $blockAboveInFront = $world->getBlockAt((int)$frontPosition->getX(), (int)$frontPosition->getY() + 1, (int)$frontPosition->getZ());

            $currentHeight = (int)floor($position->getY());
            $blockHeight = (int)floor($blockInFront->getPosition()->getY());
            $heightDiff = $blockHeight - $currentHeight;

            if ($heightDiff >= 0.5 && ($this->isClimbable($blockInFront) || $blockAboveInFront->isTransparent())) {
                $this->jump($mob, $heightDiff);
                $this->landedTick[$mobId] = $currentTick;
                return;
            }
        }
    }

    public function jump(Living $mob, float $heightDiff = 1.0): void {
        if (!$mob->isOnGround()) return;

        $jumpForce = 0.52 + ($heightDiff * 0.2);
        $mob->setMotion(new Vector3($mob->getMotion()->x, $jumpForce, $mob->getMotion()->z));
    }

    private function isClimbable(Block $block): bool {
        $climbableBlocks = [
            "pocketmine:block:slab",
            "pocketmine:block:stairs",
            "pocketmine:block:snow_layer",
            "pocketmine:block:fence", // 울타리 추가
            "pocketmine:block:glass", // 유리 추가
            "pocketmine:block:frame" // 액자 추가
        ];
        return $block->isSolid() || in_array($block->getName(), $climbableBlocks);
    }
}
