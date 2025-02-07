<?php

namespace HybridMobAI;

use pocketmine\scheduler\AsyncTask;
use pocketmine\math\Vector3;
use pocketmine\Server;
use pocketmine\entity\Creature;

class PathfinderTask extends AsyncTask {
    private float $startX, $startY, $startZ;
    private float $goalX, $goalY, $goalZ;
    private int $mobId;
    private string $algorithm, $worldName;
    private $callback; // 콜백 함수 저장
    private Pathfinder $pathfinder;

    public function __construct(float $startX, float $startY, float $startZ, float $goalX, float $goalY, float $goalZ, int $mobId, string $algorithm, string $worldName, callable $callback) {
        $this->startX = $startX;
        $this->startY = $startY;
        $this->startZ = $startZ;
        $this->goalX = $goalX;
        $this->goalY = $goalY;
        $this->goalZ = $goalZ;
        $this->mobId = $mobId;
        $this->algorithm = $algorithm;
        $this->worldName = $worldName;
        $this->callback = $callback; // 콜백 함수 초기화
        $this->pathfinder = $pathfinder;
    }

    public function onRun(): void {
        $start = new Vector3($this->startX, $this->startY, $this->startZ);
        $goal = new Vector3($this->goalX, $this->goalY, $this->goalZ);
        $path = $this->pathfinder->findPath($start, $goal, $this->algorithm);
        if (empty($path)) {
            $this->setResult(null);
        } else {
            $this->setResult($path);
        }
    }

    public function onCompletion(): void {
        $server = Server::getInstance();
        $worldName = $this->worldName; // Copy the world name

        $entityId = $this->mobId;     // Copy the entity ID
        $callback = $this->callback; // Copy the callback
        $path = $this->getResult();   // Copy the path

        $server->getScheduler()->scheduleTask(new \pocketmine\scheduler\Task(function () use ($worldName, $entityId, $path, $callback) {
            $server = Server::getInstance(); // Get Server instance inside the task
            $world = $server->getWorldManager()->getWorldByName($worldName); // Get world on main thread

            if ($world === null) {
                // Handle world not found error.  Log or take other action.
                return; // Important: Return to prevent further errors.
            }

            $entity = $world->getEntity($entityId); // Get entity on main thread
            if ($entity instanceof Creature) { // Check if the entity is valid
                call_user_func($callback, $entity, $path);
            }
        }, 0, false));
    }
}
