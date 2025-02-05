<?php

namespace HybridMobAI;

use pocketmine\scheduler\AsyncTask;
use pocketmine\math\Vector3;
use pocketmine\Server;

class PathfindingTask extends AsyncTask {
    private float $startX, $startY, $startZ;
    private float $goalX, $goalY, $goalZ;
    private int $mobId;
    private string $algorithm, $worldName;
    private static array $lastExecutionTime = [];

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
        $currentTime = microtime(true);

        // ✅ 3초 이내에 동일 몬스터가 또 실행되지 않도록 제한
        if (isset(self::$lastExecutionTime[$this->mobId]) &&
            $currentTime - self::$lastExecutionTime[$this->mobId] < 3) {
            return;
        }
        self::$lastExecutionTime[$this->mobId] = $currentTime;

        try {
            $start = new Vector3($this->startX, $this->startY, $this->startZ);
            $goal = new Vector3($this->goalX, $this->goalY, $this->goalZ);
            $pathfinder = new Pathfinder($this->worldName);
            $path = $pathfinder->findPath($start, $goal, $this->algorithm);

            // ✅ 너무 긴 경로는 최대 10개 좌표만 반환
            if (count($path) > 10) {
                $path = array_slice($path, 0, 10);
            }

            $this->setResult($path);
        } catch (\Throwable $e) {
            $this->setResult([]);
        }
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
            $this->moveRandomly($entity);
        } else {
            $nextStep = $path[1] ?? null;
            if ($nextStep !== null) {
                $entity->lookAt($nextStep);
                $motion = $nextStep->subtract($entity->getPosition())->normalize()->multiply(0.2);

                // ✅ NaN 체크 후 setMotion 적용
                if (!is_nan($motion->getX()) && !is_nan($motion->getY()) && !is_nan($motion->getZ())) {
                    $entity->setMotion($motion);
                }
            }
        }
    }

    private function moveRandomly(\pocketmine\entity\Creature $mob): void {
        $directionVectors = [
            new Vector3(1, 0, 0), new Vector3(-1, 0, 0),
            new Vector3(0, 0, 1), new Vector3(0, 0, -1)
        ];
        $randomDirection = $directionVectors[array_rand($directionVectors)];
        $mob->setMotion($randomDirection->multiply(0.15));
    }
}
