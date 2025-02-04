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
        $openList->insert($startNode, -$startNode->fCost());

        while (!$openList->isEmpty()) {
            /** @var Node $current */
            $current = $openList->extract();

            if ($current->position->equals($goal)) {
                return $this->reconstructPath($current);
            }

            $closedList[] = $current->position->asVector3();

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

            $visited[] = $current->position->asVector3();

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

            $visited[] = $current->position->asVector3();

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
                $neighbors[] = $neighbor;
            }
        }

        return $neighbors;
    }

    /** ✅ 특정 블록이 이동 가능한지 확인 **/
    private function isWalkable(Vector3 $position): bool {
        $block = $this->world->getBlockAt((int) $position->x, (int) $position->y, (int) $position->z);
        return !$block->isSolid();
    }

    /** ✅ 두 노드 간 거리 계산 **/
    private function distance(Vector3 $a, Vector3 $b): float {
        return sqrt(($a->x - $b->x) ** 2 + ($a->z - $b->z) ** 2);
    }

    /** ✅ A* 알고리즘에서 휴리스틱 계산 (맨해튼 거리) **/
    private function heuristic(Vector3 $a, Vector3 $b): float {
        return abs($a->x - $b->x) + abs($a->z - $b->z);
    }

    /** ✅ 최종 경로 재구성 **/
    private function reconstructPath(Node $node): array {
        $path = [];

        while ($node !== null) {
            $path[] = $node->position;
            $node = $node->cameFrom;
        }

        return array_reverse($path);
    }
}

/** ✅ 노드 클래스 추가 (gCost, hCost 저장) **/
class Node {
    public Vector3 $position;
    public ?Node $cameFrom;
    public float $gCost;
    public float $hCost;

    public function __construct(Vector3 $position, ?Node $cameFrom = null, float $gCost = 0, float $hCost = 0) {
        $this->position = $position;
        $this->cameFrom = $cameFrom;
        $this->gCost = $gCost;
        $this->hCost = $hCost;
    }

    public function fCost(): float {
        return $this->gCost + $this->hCost;
    }
}
