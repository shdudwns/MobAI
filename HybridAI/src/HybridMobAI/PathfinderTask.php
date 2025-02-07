<?php

namespace HybridMobAI;

use pocketmine\scheduler\AsyncTask;
use pocketmine\math\Vector3;
use pocketmine\Server;

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
        try {
            $start = new Vector3($this->startX, $this->startY, $this->startZ);
            $goal = new Vector3($this->goalX, $this->goalY, $this->goalZ);
            $pathfinder = new Pathfinder($this->worldName);
            $path = $pathfinder->findPath($start, $goal, $this->algorithm);
            $this->setResult($path);
        } catch (\Throwable $e) {
            $this->setResult([]);
        }
    }

    public function onCompletion(): void {
        $server = Server::getInstance();
        $plugin = $server->getPluginManager()->getPlugin("HybridMobAI");

        if ($plugin instanceof Main) {
            $plugin->getMobAITask()->applyPathResult($this->mobId, $this->getResult());
        }
    }
}
