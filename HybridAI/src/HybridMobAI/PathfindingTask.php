<?php

namespace HybridMobAI;

use pocketmine\math\Vector3;
use pocketmine\Server;
use pocketmine\world\World;
use pocketmine\scheduler\AsyncTask;

class PathfinderTask extends AsyncTask {
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
            $server = Server::getInstance();
            $world = $server->getWorldManager()->getWorldByName($this->worldName);

            if (!$world instanceof World) {
                throw new \Exception("World {$this->worldName} not found!");
            }

            $start = new Vector3($this->startX, $this->startY, $this->startZ);
            $goal = new Vector3($this->goalX, $this->goalY, $this->goalZ);

            $pathfinder = new Pathfinder($this->worldName);
            $path = $pathfinder->findPath($start, $goal, $this->algorithm, $world); // World 객체 전달

            $this->setResult($path);

        } catch (\Throwable $e) {
            $this->setResult(["error" => $e->getMessage()]);
            Server::getInstance()->getLogger()->error("PathfindingTask error: " . $e->getMessage() . " in " . $e->getFile() . " on line " . $e->getLine()); // 파일 이름과 줄 번호 추가
        }
    }

    public function onCompletion(): void {
        $server = Server::getInstance();
        $world = $server->getWorldManager()->getWorldByName($this->worldName);

        if ($world !== null) {
            $result = $this->getResult();

            if (is_array($result) && isset($result["error"])) { // 오류 여부 확인
                Server::getInstance()->getLogger()->error($result["error"]);
                return;
            }

            $path = $result; // $result가 배열 형태의 path를 반환하므로 바로 $path에 할당

            $entity = $world->getEntity($this->mobId);

            if ($entity instanceof \pocketmine\entity\Creature) {
                if (empty($path) || !is_array($path)) { // $path가 비어있거나 배열이 아닌 경우
                    $this->moveRandomly($entity);
                    Server::getInstance()->getLogger()->warning("No path found or invalid path for entity {$this->mobId}"); // 경고 메시지 추가
                } else {
                    $nextStep = $path[0] ?? null; // 첫 번째 좌표를 다음 목적지로 설정 (0번 인덱스)
                    if ($nextStep !== null) {
                        $entity->lookAt($nextStep);
                        $entity->setMotion($nextStep->subtract($entity->getPosition())->normalize()->multiply(0.25));
                    } else {
                        Server::getInstance()->getLogger()->warning("Next step is null for entity {$this->mobId}"); // 경고 메시지 추가
                        $this->moveRandomly($entity); // 다음 좌표가 null인 경우 무작위 이동
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
