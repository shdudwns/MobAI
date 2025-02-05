<?php

namespace HybridMobAI;

use pocketmine\scheduler\AsyncTask;
use pocketmine\math\Vector3;

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
        try {
            // 경로 탐색 알고리즘 실행
            $start = new Vector3($this->startX, $this->startY, $this->startZ);
            $goal = new Vector3($this->goalX, $this->goalY, $this->goalZ);

            // 비동기 작업 내에서는 서버 인스턴스를 직접 접근하지 않음
            $pathfinder = new Pathfinder($this->worldName);
            $path = $pathfinder->findPath($start, $goal, $this->algorithm);
            $this->setResult($path);
        } catch (\Throwable $e) {
            $this->setResult([]);
        }
    }

    public function onCompletion(): void {
        // 메인 스레드로 돌아와서 서버 인스턴스 접근
        $server = \pocketmine\Server::getInstance();
        $world = $server->getWorldManager()->getWorldByName($this->worldName);
        if ($world !== null) {
            $path = $this->getResult();
            $entity = $world->getEntity($this->mobId);

            if ($entity instanceof \pocketmine\entity\Creature) {
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
