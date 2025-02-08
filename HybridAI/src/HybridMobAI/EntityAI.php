<?php

namespace HybridMobAI;

use pocketmine\math\Vector3;
use pocketmine\world\World;
use pocketmine\entity\Living;
use pocketmine\block\Block;
use pocketmine\Server;

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

public function findPathAsync(World $world, mixed $start, mixed $goal, string $algorithm, callable $callback): void {
    try {
        // ✅ `Position` → `Vector3` 변환 강제 적용
        $start = PositionHelper::toVector3($start);
        $goal = PositionHelper::toVector3($goal);

        // ✅ PathFinderTask 실행 로그
        $this->logDebug("🛠️ PathFinderTask 실행 - Start:", $start);
        $this->logDebug("🛠️ PathFinderTask 실행 - Goal:", $goal);

        // ✅ 새로운 방식의 비동기 처리
        Server::getInstance()->getAsyncPool()->submitTask(new PathfinderTask($world->getFolderName(), $start, $goal, $algorithm, function (?array $path) use ($callback) {
            if ($path !== null) {
                Server::getInstance()->getScheduler()->scheduleTask(new SynchronizedTask(function () use ($callback, $path) {
                    $callback($path);
                }));
            }
        }));
    } catch (\Throwable $e) {
        $this->logDebug("❌ PathFinderTask 실행 중 오류 발생", $e->getMessage());
    }
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
