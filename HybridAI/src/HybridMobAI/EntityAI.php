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

public function moveAlongPath(Living $mob, array $path): void {
    if (empty($path)) return;

    $nextPosition = array_shift($path);
    if ($nextPosition instanceof Vector3) {
        $mob->setMotion($nextPosition->subtractVector($mob->getPosition())->normalize()->multiply(0.2));
        $mob->lookAt($nextPosition);
    }
}
    public function findPathAsync(World $world, Vector3 $start, Vector3 $goal, string $algorithm, callable $callback): void {
    $task = new PathfinderTask($world->getFolderName(), $start, $goal, $algorithm);
    Server::getInstance()->getAsyncPool()->submitTask($task);

    Server::getInstance()->getAsyncPool()->addWorkerStartHook(function() use ($task, $callback) {
        if (($path = $task->getResult()) !== null) {
            $callback($path);
        }
    });
}
}
