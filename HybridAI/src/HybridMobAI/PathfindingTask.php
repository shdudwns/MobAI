<?php

namespace HybridMobAI;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\entity\Creature;
use pocketmine\math\Vector3;

class PathfindingTask extends AsyncTask {

    private $start;
    private $goal;
    private $grid;
    private $mobId;
    private $algorithm;

    public function __construct($start, $goal, $grid, $mobId, $algorithm) {
        $this->start = $start;
        $this->goal = $goal;
        $this->grid = $grid;
        $this->mobId = $mobId;
        $this->algorithm = $algorithm;
    }

    public function onRun(): void {
        $pathfinder = new Pathfinder();
        $path = $pathfinder->findPath($this->start, $this->goal, $this->grid, $this->algorithm);
        $this->setResult($path);
    }

    public function onCompletion(): void {
        $server = Server::getInstance();
        $path = $this->getResult();
        $mob = $server->getWorldManager()->getWorldById($this->mobId);
        if ($path === null) {
            if ($mob instanceof Creature) {
                $this->moveRandomly($mob); // 랜덤 이동
            }
        } else {
            if ($mob instanceof Creature) {
                $nextStep = $path[1];
                $mob->lookAt($nextStep);
                $mob->move($nextStep->subtractVector($mob->getPosition())->normalize()->multiply(0.25));
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
        $mob->move($randomDirection->multiply(0.25));
    }
}
