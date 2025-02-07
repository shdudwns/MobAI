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
    public function findPathAsync(World $world, $start, $goal, string $algorithm, callable $callback): void {
    // ✅ Position 또는 배열 형태의 좌표가 들어오면 Vector3로 변환
    if ($start instanceof Position) {
        $start = new Vector3((float)$start->x, (float)$start->y, (float)$start->z);
    } elseif (is_array($start)) {
        $start = new Vector3((float)$start[0], (float)$start[1], (float)$start[2]);
    }

    if ($goal instanceof Position) {
        $goal = new Vector3((float)$goal->x, (float)$goal->y, (float)$goal->z);
    } elseif (is_array($goal)) {
        $goal = new Vector3((float)$goal[0], (float)$goal[1], (float)$goal[2]);
    }


    $task = new PathfinderTask($world->getFolderName(), $start, $goal, $algorithm);
    $task->callback = $callback; // 콜백 저장
    Server::getInstance()->getAsyncPool()->submitTask($task);

    // WorkerStartHook 제거: onCompletion에서 처리
    $task->onCompletion(Server::getInstance());

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
