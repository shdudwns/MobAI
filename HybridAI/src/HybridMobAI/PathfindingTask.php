<?php

namespace HybridMobAI;

use pocketmine\scheduler\AsyncTask;
use pocketmine\scheduler\ClosureTask;
use pocketmine\math\Vector3;
use pocketmine\Server;
use pocketmine\entity\Creature;
use pocketmine\world\Position;

class PathfindingTask extends AsyncTask {
    private float $startX, $startY, $startZ;
    private float $goalX, $goalY, $goalZ;
    private $grid;
    private $mobId;
    private $algorithm;
    private $worldName;
    private $callback;

    public function __construct(Position $start, Position $goal, $grid, $mobId, $algorithm, $worldName, callable $callback) {
        $this->startX = $start->getX();
        $this->startY = $start->getY();
        $this->startZ = $start->getZ();
        $this->goalX = $goal->getX();
        $this->goalY = $goal->getY();
        $this->goalZ = $goal->getZ();
        $this->grid = $grid;
        $this->mobId = $mobId;
        $this->algorithm = $algorithm;
        $this->worldName = $worldName;
        $this->callback = $callback;
    }

    public function onRun(): void {
        $pathfinder = new Pathfinder();
        $start = new Vector3($this->startX, $this->startY, $this->startZ);
        $goal = new Vector3($this->goalX, $this->goalY, $this->goalZ);
        $path = $pathfinder->findPath($start, $goal, $this->grid, $this->algorithm);
        $this->setResult($path);
    }

    public function onCompletion(): void {
        $server = Server::getInstance();
        $world = $server->getWorldManager()->getWorldByName($this->worldName);
        if ($world === null) {
            return;
        }
        $entity = $world->getEntity($this->mobId);
        if (!$entity instanceof Creature) {
            return;
        }

        $path = $this->getResult();
        $callback = $this->callback;

        if ($path === null) {
            $server->getScheduler()->scheduleTask(new ClosureTask(function () use ($callback, $entity) {
                if (is_callable($callback)) {
                    call_user_func($callback, $entity, null);
                }
            }));
        } else {
            $server->getScheduler()->scheduleTask(new ClosureTask(function () use ($callback, $entity, $path) {
                if (is_callable($callback)) {
                    call_user_func($callback, $entity, $path);
                }
            }));
        }
    }
}
