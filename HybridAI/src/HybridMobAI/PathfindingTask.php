<?php

namespace HybridMobAI;

use pocketmine\scheduler\AsyncTask;
use pocketmine\math\Vector3;
use pocketmine\Server;
use pocketmine\entity\Creature;
use pocketmine\world\Position;

class PathfindingTask extends AsyncTask {
    private float $startX, $startY, $startZ; // float 좌표 저장
    private float $goalX, $goalY, $goalZ;   // float 좌표 저장
    private $grid;
    private $mobId;
    private $algorithm;
    private $worldName;
    private $callback;

    public function __construct(Position $start, Position $goal, $grid, $mobId, $algorithm, $worldName, callable $callback) {
        $this->startX = $start->getX(); // 좌표 추출
        $this->startY = $start->getY();
        $this->startZ = $start->getZ();
        $this->goalX = $goal->getX();   // 좌표 추출
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
        $start = new Vector3($this->startX, $this->startY, $this->startZ); // Vector3 생성
        $goal = new Vector3($this->goalX, $this->goalY, $this->goalZ);   // Vector3 생성
        $path = $pathfinder->findPath($start, $goal, $this->grid, $this->algorithm);
        $this->setResult($path);
    }


    public function onCompletion(): void {
        $server = Server::getInstance();
        // ... (world and entity retrieval)

        $path = $this->getResult();

        if ($path === null) {
            $callback = $this->callback; // 복사!
            $entity = $entity;          // 복사!
            $server->getScheduler()->scheduleTask(new \pocketmine\scheduler\Task(function () use ($callback, $entity) {
                if (is_callable($callback)) {
                    call_user_func($callback, $entity, null);
                }
            }, 0, false));
        } else {
            $callback = $this->callback; // 복사!
            $entity = $entity;          // 복사!
            $path = $path;              // 복사!
            $server->getScheduler()->scheduleTask(new \pocketmine\scheduler\Task(function () use ($callback, $entity, $path) {
                if (is_callable($callback)) {
                    call_user_func($callback, $entity, $path);
                }
            }, 0, false));
        }
    }
}
