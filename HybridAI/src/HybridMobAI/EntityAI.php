<?php

namespace HybridMobAI;

use pocketmine\math\Vector3;
use pocketmine\world\World;
use pocketmine\entity\Living;
use pocketmine\block\Block;
use pocketmine\Server;
use pocketmine\scheduler\AsyncTask;

class EntityAI {
    private bool $enabled = false; // AI 활성화 여부
    private array $path = []; // A* 경로
    private ?Vector3 $target = null; // 목표 위치
    private array $entityPaths = [];
    
    public function setEnabled(bool $enabled): void {
        $this->enabled = $enabled;
    }

    public function isEnabled(): bool {
        return $this->enabled;
    }

    public function findPathAsync(World $world, Vector3 $start, Vector3 $goal, string $algorithm, callable $callback): void {
        $worldName = $world->getFolderName();
        Server::getInstance()->getAsyncPool()->submitTask(new class($worldName, $start, $goal, $algorithm, $callback) extends AsyncTask {
            private string $worldName;
            private Vector3 $start;
            private Vector3 $goal;
            private string $algorithm;
            private $callback;

            public function __construct(string $worldName, Vector3 $start, Vector3 $goal, string $algorithm, callable $callback) {
                $this->worldName = $worldName;
                $this->start = $start;
                $this->goal = $goal;
                $this->algorithm = $algorithm;
                $this->callback = $callback;
            }

            public function onRun(): void {
            $world = Server::getInstance()->getWorldManager()->getWorldByName($this->worldName); // 월드 객체 가져오기
            if ($world instanceof World) { // 월드가 로드되었는지 확인
                $pathfinder = new Pathfinder($world);
                $path = match ($this->algorithm) {
                    "A*" => $pathfinder->findPathAStar($this->start, $this->goal),
                    "Dijkstra" => $pathfinder->findPathDijkstra($this->start, $this->goal),
                    "Greedy" => $pathfinder->findPathGreedy($this->start, $this->goal),
                    "BFS" => $pathfinder->findPathBFS($this->start, $this->goal),
                    "DFS" => $pathfinder->findPathDFS($this->start, $this->goal),
                    default => null,
                };
                $this->setResult($path);
            } else {
                $this->setResult(null); // 월드가 로드되지 않았으면 null 반환
            }
        }

            public function onCompletion(): void {
                ($this->callback)($this->getResult());
            }
        });
    }

    public function findPath(World $world, Vector3 $start, Vector3 $goal, string $algorithm): ?array {
    $pathfinder = new Pathfinder($world);

    switch ($algorithm) {
        case "A*":
            return $pathfinder->findPathAStar($start, $goal);
        case "Dijkstra":
            return $pathfinder->findPathDijkstra($start, $goal);
        case "Greedy":
            return $pathfinder->findPathGreedy($start, $goal);
        case "BFS":
            return $pathfinder->findPathBFS($start, $goal);
        case "DFS":
            return $pathfinder->findPathDFS($start, $goal);
        default:
            return null;
    }
}
    private function logDebug(string $message, mixed $data = null): void {
    $logMessage = "[" . date("Y-m-d H:i:s") . "] " . $message;
    if ($data !== null) {
        $logMessage .= " " . print_r($data, true);
    }
    $logMessage .= "\n";
    file_put_contents("debug_log.txt", $logMessage, FILE_APPEND);
}

public function setPath(Living $mob, array $path): void {
    $this->entityPaths[$mob->getId()] = $path;
}

public function hasPath(Living $mob): bool {
    return isset($this->entityPaths[$mob->getId()]);
}

public function moveAlongPath(Living $mob): void {
    if (!isset($this->entityPaths[$mob->getId()]) || empty($this->entityPaths[$mob->getId()])) {
        return;
    }

    $nextPosition = array_shift($this->entityPaths[$mob->getId()]);
    if ($nextPosition instanceof Vector3) {
        $mob->setMotion($nextPosition->subtractVector($mob->getPosition())->normalize()->multiply(0.2));
        $mob->lookAt($nextPosition);
    }
}
}
