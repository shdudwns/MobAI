<?php

namespace HybridMobAI;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\entity\Creature;
use pocketmine\math\Vector3;

class PathfindingTask extends AsyncTask {
    private Vector3 $start;
    private Vector3 $goal;
    private string $algorithm;
    private int $mobId;

    public function __construct(Vector3 $start, Vector3 $goal, int $mobId, string $algorithm) {
        $this->start = $start;
        $this->goal = $goal;
        $this->mobId = $mobId;
        $this->algorithm = $algorithm;
    }

    public function onRun(): void {
        $pathfinder = new Pathfinder();
        $path = $pathfinder->findPath($this->start, $this->goal, $this->algorithm);
        $this->setResult($path);
    }

    public function onCompletion(): void {
        $server = Server::getInstance();
        $path = $this->getResult();
        $entity = $server->getWorldManager()->findEntity($this->mobId); // ✅ Entity ID를 기반으로 찾음

        if ($entity instanceof Creature) {
            if ($path === null) {
                $this->moveRandomly($entity); // 랜덤 이동
            } else {
                $nextStep = $path[1] ?? null;
                if ($nextStep !== null) {
                    $entity->lookAt($nextStep);
                    $entity->setMotion($nextStep->subtract($entity->getPosition())->normalize()->multiply(0.25)); // ✅ `move()` 대신 `setMotion()` 사용
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
        $mob->setMotion($randomDirection->multiply(0.15)); // ✅ `move()` 대신 `setMotion()` 사용
    }
}
