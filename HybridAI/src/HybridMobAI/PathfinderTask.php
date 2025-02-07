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

    public function __construct(float $startX, float $startY, float $startZ, float $goalX, float $goalY, float $goalZ, int $mobId, string $algorithm, string $worldName) {
        $this->startX = $startX;
        $this->startY = $startY;
        $this->startZ = $startZ;
        $this->goalX = $goalX;
        $this->goalY = $goalY;
        $this->goalZ = $goalZ;
        $this->mobId = $mobId;
        $this->algorithm = $algorithm;
        $this->worldName = $worldName;
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

        if ($world === null) return;

        $entity = $world->getEntity($this->mobId);
        if ($entity === null || !$entity->isAlive()) return;

        $path = $this->getResult();
        $plugin = $server->getPluginManager()->getPlugin("HybridMobAI");

        if ($plugin instanceof Main) {
            $mobAITask = $plugin->getMobAITask();

            // 경로를 현재 경로로 저장
            if ($path === null) {
                // 경로를 찾지 못하면 랜덤 이동 실행
                $mobAITask->moveRandomly($entity);
            } else {
                // 정상적으로 경로를 찾았을 경우 이동
                $mobAITask->currentPaths[$this->mobId] = $path; // 경로 저장
            }
        }
    }
}
