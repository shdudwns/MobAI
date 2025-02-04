<?php

namespace HybridMobAI;

use pocketmine\math\Vector3;
use pocketmine\world\World;
use SplPriorityQueue;
use SplQueue;
use SplStack;

class Pathfinder {

    private World $world;
    
    public function __construct(World $world) {
        $this->world = $world;
    }

    public function findPath(Vector3 $start, Vector3 $goal, string $algorithm): ?array {
        switch ($algorithm) {
            case "AStar":
                return $this->findPathAStar($start, $goal);
            case "BFS":
                return $this->findPathBFS($start, $goal);
            case "DFS":
                return $this->findPathDFS($start, $goal);
            default:
                return null;
        }
    }

    /** ✅ A* 알고리즘 구현 **/
    private function findPathAStar(Vector3 $start, Vector3 $goal): ?array {
        $openList = new SplPriorityQueue();
        $closedList = [];
        $startNode = new Node($start, null, 0, $this->heuristic($start, $goal));
        $openList->insert($startNode, -$startNode->fCost()); // ✅ 낮은 fCost()가 우선순위 되도록 수정

        while (!$openList->isEmpty()) {
            /** @var Node $current */
            $current = $openList->extract();

            if ($current->position->equals($goal)) {
                return $this->reconstructPath($current);
            }

            $closedList[] = clone $current->position; // ✅ asVector3() 제거 후 clone 사용

            foreach ($this->getNeighbors($current->position) as $neighbor) {
                if (in_array($neighbor, $closedList)) {
                    continue;
                }

                $gCost = $current->gCost + $this->distance($current->position, $neighbor);
                $neighborNode = new Node($neighbor, $current, $gCost, $this->heuristic($neighbor, $goal));
                $openList->insert($neighborNode, -$neighborNode->fCost());
            }
        }

        return null;
    }

    /** ✅ BFS 알고리즘 구현 **/
    private function findPathBFS(Vector3 $start, Vector3 $goal): ?array {
        $queue = new SplQueue();
        $queue->enqueue(new Node($start));
        $visited = [];

        while (!$queue->isEmpty()) {
            /** @var Node $current */
            $current = $queue->dequeue();

            if ($current->position->equals($goal)) {
                return $this->reconstructPath($current);
            }

            $visited[] = clone $current->position; // ✅ clone 사용하여 값 보호

            foreach ($this->getNeighbors($current->position) as $neighbor) {
                if (!in_array($neighbor, $visited)) {
                    $queue->enqueue(new Node($neighbor, $current));
                }
            }
        }

        return null;
    }

    /** ✅ DFS 알고리즘 구현 **/
    private function findPathDFS(Vector3 $start, Vector3 $goal): ?array {
        $stack = new SplStack();
        $stack->push(new Node($start));
        $visited = [];

        while (!$stack->isEmpty()) {
            /** @var Node $current */
            $current = $stack->pop();

            if ($current->position->equals($goal)) {
                return $this->reconstructPath($current);
            }

            $visited[] = clone $current->position; // ✅ clone 사용하여 값 보호

            foreach ($this->getNeighbors($current->position) as $neighbor) {
                if (!in_array($neighbor, $visited)) {
                    $stack->push(new Node($neighbor, $current));
                }
            }
        }

        return null;
    }

    /** ✅ 이동 가능한 이웃 노드 찾기 **/
    private function getNeighbors(Vector3 $position): array {
        $neighbors = [];
        $directions = [
            new Vector3(1, 0, 0), new Vector3(-1, 0, 0),
            new Vector3(0, 0, 1), new Vector3(0, 0, -1)
        ];

        foreach ($directions as $dir) {
            $neighbor = $position->add($dir->x, $dir->y, $dir->z);
            if ($this->isWalkable($neighbor)) {
                $neighbors[] = clone $neighbor;
            }
        }

        return $neighbors;
    }

    private function isWalkable(Vector3 $position): bool {
        $block = $this->world->getBlockAt((int) $position->x, (int) $position->y, (int) $position->z);
        return !$block->isSolid();
    }

    private function distance(Vector3 $a, Vector3 $b): float {
        return sqrt(($a->x - $b->x) ** 2 + ($a->z - $b->z) ** 2);
    }

    private function heuristic(Vector3 $a, Vector3 $b): float {
        return abs($a->x - $b->x) + abs($a->z - $b->z);
    }

    private function reconstructPath(Node $node): array {
        $path = [];
        while ($node !== null) {
            $path[] = clone $node->position;
            $node = $node->cameFrom;
        }
        return array_reverse($path);
    }
}

class Node {
    public Vector3 $position;
    public ?Node $cameFrom;
    public float $gCost;
    public float $hCost;

    public function __construct(Vector3 $position, ?Node $cameFrom = null, float $gCost = 0, float $hCost = 0) {
        $this->position = clone $position; // ✅ clone을 사용하여 불변성 유지
        $this->cameFrom = $cameFrom;
        $this->gCost = $gCost;
        $this->hCost = $hCost;
    }

    public function fCost(): float {
        return $this->gCost + $this->hCost;
    }
}
