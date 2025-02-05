<?php

namespace HybridMobAI;

use pocketmine\scheduler\AsyncTask;
use pocketmine\math\Vector3;
use pocketmine\Server;

class PathfindingTask extends AsyncTask {
    private float $startX;
    private float $startY;
    private float $startZ;
    private float $goalX;
    private float $goalY;
    private float $goalZ;
    private int $mobId;
    private string $algorithm;
    private string $worldName;

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
        
        // ✅ 같은 몬스터에 대해 일정 시간 이후에만 실행되도록 제한 (2초마다 실행)
        if (isset(self::$lastExecutionTime[$this->mobId]) &&
            $currentTime - self::$lastExecutionTime[$this->mobId] < 2) {
            return;
        }
        self::$lastExecutionTime[$this->mobId] = $currentTime;

        try {
            $start = new Vector3($this->startX, $this->startY, $this->startZ);
            $goal = new Vector3($this->goalX, $this->goalY, $this->goalZ);
            
            // ✅ Pathfinder 인스턴스 생성
            $pathfinder = new Pathfinder($this->worldName);
            $path = $pathfinder->findPath($start, $goal, $this->algorithm);

            $this->setResult($path);
        } catch (\Throwable $e) {
            $this->setResult([]);
        }
    }

    public function onCompletion(): void {
        $server = Server::getInstance();
        $world = $server->getWorldManager()->getWorldByName($this->worldName);

        if ($world === null) {
            return; // ✅ 월드가 없으면 종료
        }

        $path = $this->getResult();
        $entity = $world->getEntity($this->mobId);

        if ($entity === null || !$entity->isAlive()) {
            return; // ✅ 엔티티가 없거나 죽었으면 실행 중단
        }

        if (empty($path)) {
            $this->moveRandomly($entity);
        } else {
            $nextStep = $path[1] ?? null;
            if ($nextStep !== null) {
                $entity->lookAt($nextStep);
                $entity->setMotion($nextStep->subtract($entity->getPosition())->normalize()->multiply(0.25));
            }
        }
    }

    private function moveRandomly(\pocketmine\entity\Creature $mob): void {
        $directionVectors = [
            new Vector3(1, 0, 0),
            new Vector3(-1, 0, 0),
            new Vector3(0, 0, 1),
            new Vector3(0, 0, -1)
        ];
        $randomDirection = $directionVectors[array_rand($directionVectors)];
        $mob->setMotion($randomDirection->multiply(0.15));
    }
}
