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
            $server->getLogger()->warning("World {$this->worldName} not found!"); // 월드 경고 메시지 추가
            return;
        }

        $entity = $world->getEntity($this->mobId);
        if ($entity === null || !$entity->isAlive()) {
            $server->getLogger()->warning("Entity {$this->mobId} not found or dead!"); // 엔티티 경고 메시지 추가
            return;
        }

        $path = $this->getResult();

        // 콜백 함수가 설정되었는지 확인 후 호출
        if (is_callable($this->callback)) {
            $server->getScheduler()->scheduleTask(new \pocketmine\scheduler\Task(function () use ($entity, $path, $this->callback) {
                call_user_func($this->callback, $entity, $path);
            }, 0, false));
        } else {
            $server->getLogger()->warning("Callback function is not set for entity {$this->mobId}!"); // 콜백 경고 메시지 추가
        }
    }
}
