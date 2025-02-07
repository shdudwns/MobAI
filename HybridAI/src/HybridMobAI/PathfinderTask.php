<?php

namespace HybridMobAI;

use pocketmine\scheduler\AsyncTask;
use pocketmine\math\Vector3;
use pocketmine\Server;
use pocketmine\entity\Creature;
use pocketmine\player\Player;

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

        if (empty($path)) {
            $this->setResult(null);
        } else {
            $this->setResult($path);
        }
    }

    public function onCompletion(): void {
        $server = Server::getInstance();
        $world = $server->getWorldManager()->getWorldByName($this->worldName);
        // PathfinderTask.php onCompletion() 내부
        $server->getLogger()->info("경로 발견: " . count(int($path)) . "개 스텝");
        if ($path !== null) {
            $plugin->getLogger()->debug("첫 번째 스텝: " . $nextStep->__toString());
        }

        if ($world === null) return;

        $entity = $world->getEntity($this->mobId);
        if ($entity === null || !$entity->isAlive()) return;

        $path = $this->getResult();
        $plugin = $server->getPluginManager()->getPlugin("HybridMobAI");

        if ($plugin instanceof Main) {
            $mobAITask = $plugin->getMobAITask();

            if ($path === null) {
                // ✅ 경로를 찾지 못하면 랜덤 이동 실행
                $mobAITask->moveRandomly($entity);
            } else {
                // ✅ 정상적으로 경로를 찾았을 경우 이동
                if ($entity instanceof Creature) {
                    $nextStep = $path[0] ?? null;
                    if ($nextStep !== null) {
                        $entity->lookAt($nextStep);
                        $motion = $nextStep->subtractVector($entity->getPosition())->normalize()->multiply(0.15);
                        if (!is_nan($motion->getX()) && !is_nan($motion->getY()) && !is_nan($motion->getZ())) {
                            $entity->setMotion($motion);
                        }
                    }
                }
            }
        }
    }
}
