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
        
        try {
            $path = $pathfinder->findPath($start, $goal, $this->algorithm);
        } catch (\Throwable $e) {
            $this->setResult([]);
            return;
        }

        // ✅ 너무 긴 경로 제한 (최대 10칸)
        if (is_array($path) && count($path) > 10) {
            $path = array_slice($path, 0, 10);
        }

        $this->setResult($path);
    }

    public function onCompletion(): void {
        $server = Server::getInstance();
        $world = $server->getWorldManager()->getWorldByName($this->worldName);

        if ($world === null) {
            return; // ✅ 월드가 없으면 실행 종료
        }

        $entity = $world->getEntity($this->mobId);

        if ($entity === null || !$entity->isAlive()) {
            return; // ✅ 엔티티가 없거나 죽었으면 실행 중단
        }

        $path = $this->getResult();

        if (empty($path)) {
            // ✅ 경로를 찾지 못한 경우 랜덤 이동 실행
            if ($entity instanceof Creature) {
                $plugin = $server->getPluginManager()->getPlugin("HybridMobAI");
                if ($plugin instanceof Main) {
                    $mobAITask = $plugin->getMobAITask();
                    if ($mobAITask !== null) {
                        $mobAITask->moveRandomly($entity);
                    }
                }
            }
        } else {
            if ($entity instanceof Creature) {
                $nextStep = $path[1] ?? null;
                if ($nextStep !== null) {
                    $entity->lookAt($nextStep);
                    $motion = $nextStep->subtractVector($entity->getPosition())->normalize()->multiply(0.2);

                    if (!is_nan($motion->getX()) && !is_nan($motion->getY()) && !is_nan($motion->getZ())) {
                        $entity->setMotion($motion);
                    }
                }
            }
        }
    }
}
