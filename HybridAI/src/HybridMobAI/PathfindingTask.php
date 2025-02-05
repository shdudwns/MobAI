<?php

namespace HybridMobAI;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\math\Vector3;
use pocketmine\world\World;

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
            $server = Server::getInstance();
            $world = $server->getWorldManager()->getWorldByName($this->worldName);
            $pathfinder = new Pathfinder($world); // World 객체 전달
            $path = $pathfinder->findPath($start, $goal, $this->algorithm);
            $this->setResult($path);
        } catch (\Throwable $e) {
            $this->setResult([]);
            $server->getLogger()->error("PathfindingTask error: " . $e->getMessage());
        }
    }

    public function onCompletion(): void {
        $server = Server::getInstance();
        $path = $this->getResult();
        $entity = $server->getWorldManager()->findEntity($this->mobId);

        if ($entity instanceof Creature) {
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

    private function moveRandomly(Creature $mob): void {
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
