<?php

namespace HybridMobAI;

use pocketmine\scheduler\AsyncTask;
use pocketmine\math\Vector3;
use pocketmine\Server;
use pocketmine\entity\Creature;

class PathfindingTask extends AsyncTask {
    private $start;
    private $goal;
    private $grid;
    private $mobId;
    private $algorithm;
    private $worldName; // 월드 이름 추가
    private $callback;

    public function __construct($start, $goal, $grid, $mobId, $algorithm, $worldName, callable $callback) {
        $this->start = $start;
        $this->goal = $goal;
        $this->grid = $grid;
        $this->mobId = $mobId;
        $this->algorithm = $algorithm;
        $this->worldName = $worldName; // 월드 이름 초기화
        $this->callback = $callback;
    }

    public function onRun(): void {
        $pathfinder = new Pathfinder();
        $path = $pathfinder->findPath($this->start, $this->goal, $this->grid, $this->algorithm);
        $this->setResult($path);
    }

    public function onCompletion(): void {
        $server = Server::getInstance();
        $world = $server->getWorldManager()->getWorldByName($this->worldName); // 월드 이름으로 월드 객체 가져오기

        if (!$world) {
            $server->getLogger()->warning("World not found: " . $this->worldName);
            return;
        }

        $path = $this->getResult();
        $entity = $world->getEntity($this->mobId);

        if (!($entity instanceof Creature)) { // Creature instance 확인
            return;
        }

        if ($path === null) {
            $callback = $this->callback;
            $server->getScheduler()->scheduleTask(new \pocketmine\scheduler\Task(function () use ($callback, $entity) {
                if (is_callable($callback)) {
                    call_user_func($callback, $entity, null);
                }
            }, 0, false));
        } else {
            $callback = $this->callback;
            $server->getScheduler()->scheduleTask(new \pocketmine\scheduler\Task(function () use ($callback, $entity, $path) {
                if (is_callable($callback)) {
                    call_user_func($callback, $entity, $path);
                }
            }, 0, false));
        }
    }
}
