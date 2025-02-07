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
    }

    public function onRun(): void {
        $pathfinder = new Pathfinder();
        $start = new Vector3($this->startX, $this->startY, $this->startZ);
        $goal = new Vector3($this->goalX, $this->goalY, $this->goalZ);
        $path = $pathfinder->findPath($start, $goal, $this->algorithm);

        if (empty($path)) {
            $this->setResult(null);
        } else {
            $this->setResult($path);
        }
    }

    public function onCompletion(): void {
        $server = Server::getInstance();
        $world = $server->getWorldManager()->getWorldByName($this->worldName);

        if ($world === null) {
            $server->getLogger()->warning("World {$this->worldName} not found!");
            return;
        }

        $entity = $world->getEntity($this->mobId);
        if ($entity === null || !$entity->isAlive()) {
            $server->getLogger()->warning("Entity {$this->mobId} not found or dead!");
            return;
        }

        $path = $this->getResult();

        // Correct way to handle callback:
        $callback = $this->callback; // Copy the callback
        $entityId = $this->mobId;     // Copy the entity ID

        if (is_callable($callback)) {
            $server->getScheduler()->scheduleTask(new \pocketmine\scheduler\Task(function () use ($entityId, $path, $callback, $world) {
                $entity = $world->getEntity($entityId); // Get the entity on the main thread!
                if ($entity instanceof Creature) { // Check if the entity is valid
                    call_user_func($callback, $entity, $path);
                }
            }, 0, false));
        } else {
            $server->getLogger()->warning("Callback function is not set for entity {$this->mobId}!");
        }
    }
}
