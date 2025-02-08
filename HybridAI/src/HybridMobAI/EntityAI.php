<?php

namespace HybridMobAI;

use pocketmine\math\Vector3;
use pocketmine\world\World;
use pocketmine\entity\Living;
use pocketmine\block\Block;

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
    public function findPathAsync(World $world, Vector3 $start, Vector3 $goal, string $algorithm, callable $callback): void {
    // ✅ Position인지 체크하고 Vector3로 강제 변환
    if (!$start instanceof Vector3) {
        var_dump("⚠️ 변환 전 Start 값:", $start);
        $start = new Vector3((float)$start->x, (float)$start->y, (float)$start->z);
        var_dump("✅ 변환 후 Start 값:", $start);
    }

    if (!$goal instanceof Vector3) {
        var_dump("⚠️ 변환 전 Goal 값:", $goal);
        $goal = new Vector3((float)$goal->x, (float)$goal->y, (float)$goal->z);
        var_dump("✅ 변환 후 Goal 값:", $goal);
    }

    // ✅ 디버깅 로그 추가
    var_dump("🛠️ PathFinderTask 생성 - Start:", $start, "Goal:", $goal);

    $task = new PathfinderTask($world->getFolderName(), $start, $goal, $algorithm);
    Server::getInstance()->getAsyncPool()->submitTask($task);

    Server::getInstance()->getAsyncPool()->addWorkerStartHook(function() use ($task, $callback) {
        if (($path = $task->getResult()) !== null) {
            $callback($path);
        }
    });
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
