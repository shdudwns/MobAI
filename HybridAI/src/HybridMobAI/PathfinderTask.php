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

        $this->setResult($path);
    }

    public function onCompletion(): void {
        $server = Server::getInstance();
        $world = $server->getWorldManager()->getWorldByName($this->worldName);

        if ($world === null) {
            return;
        }

        $entity = $world->getEntity($this->mobId);

        if ($entity === null || !$entity->isAlive()) {
            return;
        }

        $path = $this->getResult();

        if (empty($path)) {
            if ($entity instanceof Creature) {
                /** ✅ Main 클래스에서 MobAITask 가져오기 */
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
