<?php

namespace HybridMobAI;

use pocketmine\scheduler\AsyncTask;
use pocketmine\math\Vector3;
use pocketmine\Server;

class PathfinderTask extends AsyncTask {
    private float $startX, $startY, $startZ;
    private float $goalX, $goalY, $goalZ;
    private int $mobId;
    private string $algorithm;
    private string $worldName;

    public function __construct(
        float $startX, float $startY, float $startZ,
        float $goalX, float $goalY, float $goalZ,
        int $mobId, string $algorithm, string $worldName
    ) {
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
        $pathfinder = new Pathfinder(); // Ensure Pathfinder class exists and is correctly implemented.
        $start = new Vector3($this->startX, $this->startY, $this->startZ);
        $goal = new Vector3($this->goalX, $this->goalY, $this->goalZ);

        try {
            $path = $pathfinder->findPath($start, $goal, $this->algorithm);
            $this->setResult($path);
        } catch (\Exception $e) {
            $this->setResult(null); // Indicate pathfinding failure.
            $server = Server::getInstance();
            $server->getLogger()->error("Pathfinding error: " . $e->getMessage());
        }
    }

    public function onCompletion(): void {
        $server = Server::getInstance();
        $world = $server->getWorldManager()->getWorldByName($this->worldName);

        if (!$world) {
            $server->getLogger()->warning("World not found: " . $this->worldName);
            return;
        }

        $entity = $world->getEntity($this->mobId);

        if (!$entity instanceof Zombie || !$entity->isAlive()) {
            return;
        }

        $path = $this->getResult();

        if ($path === null || empty($path)) { // Check for both null (error) and empty path.
            $plugin = $server->getPluginManager()->getPlugin("HybridMobAI");
            if ($plugin instanceof Main) {
                $mobAITask = $plugin->getMobAITask();
                if ($mobAITask !== null) {
                    $mobAITask->moveRandomly($entity);
                } else {
                    $server->getLogger()->warning("MobAITask not found.");
                }
            } else {
                $server->getLogger()->warning("HybridMobAI plugin not found.");
            }
        } else {
            // Path found
            $plugin = $server->getPluginManager()->getPlugin("HybridMobAI");
            if ($plugin instanceof Main) {
                $mobAITask = $plugin->getMobAITask();
                if ($mobAITask !== null) {
                    $mobAITask->applyPathResult($this->mobId, $path); // Pass the path to MobAITask
                } else {
                    $server->getLogger()->warning("MobAITask not found.");
                }
            } else {
                $server->getLogger()->warning("HybridMobAI plugin not found.");
            }
        }
    }
}
