<?php

namespace HybridMobAI;

use pocketmine\math\Vector3;
use SplPriorityQueue;
use SplQueue;
use SplStack;

class Pathfinder {
    private const MAX_ITERATIONS = 5000; // ✅ 탐색 최대 반복 횟수 제한
    private const MAX_PATH_LENGTH = 20; // ✅ 경로 최대 길이 제한

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

    /** ✅ A* 알고리즘 최적화 */
    private function findPathAStar(Vector3 $start, Vector3 $goal): ?array {
    $openList = new SplPriorityQueue();
    $closedList = [];
    $startNode = new Node($start, null, 0, $this->heuristic($start, $goal));
    $openList->insert($startNode, -$startNode->fCost());
    
    $maxNodes = 1000; // ✅ 최대 노드 탐색 제한 (서버 과부하 방지)
    $nodeCount = 0;

    while (!$openList->isEmpty()) {
        if ($nodeCount++ > $maxNodes) {
            return null; // ✅ 노드 수 초과하면 경로 찾기 실패
        }

        $current = $openList->extract();
        if ($this->isSamePosition($current->position, $goal)) {
            return $this->reconstructPath($current);
        }

        $closedList[$this->getPositionHash($current->position)] = true;

        foreach ($this->getNeighbors($current->position) as $neighbor) {
            $neighborHash = $this->getPositionHash($neighbor);
            if (isset($closedList[$neighborHash])) {
                continue;
            }

            $gCost = $current->gCost + $this->distance($current->position, $neighbor);
            $neighborNode = new Node($neighbor, $current, $gCost, $this->heuristic($neighbor, $goal));
            $openList->insert($neighborNode, -$neighborNode->fCost());
        }
    }

    return null;
}

    /** ✅ BFS 최적화 */
    private function findPathBFS(Vector3 $start, Vector3 $goal): ?array {
        $queue = new SplQueue();
        $queue->enqueue(new Node($start));
        $visited = [];

        $iterations = 0;
        while (!$queue->isEmpty()) {
            if (++$iterations > self::MAX_ITERATIONS) {
                return null; // ✅ 무한 루프 방지
            }

            /** @var Node $current */
            $current = $queue->dequeue();

            if ($this->isSamePosition($current->position, $goal)) {
                return $this->reconstructPath($current);
            }

            $visited[$this->getPositionHash($current->position)] = true;

            foreach ($this->getNeighbors($current->position) as $neighbor) {
                $neighborHash = $this->getPositionHash($neighbor);
                if (!isset($visited[$neighborHash])) {
                    $queue->enqueue(new Node($neighbor, $current));
                }
            }
        }

        return null;
    }

    /** ✅ DFS 최적화 */
    private function findPathDFS(Vector3 $start, Vector3 $goal): ?array {
        $stack = new SplStack();
        $stack->push(new Node($start));
        $visited = [];

        $iterations = 0;
        while (!$stack->isEmpty()) {
            if (++$iterations > self::MAX_ITERATIONS) {
                return null; // ✅ 무한 루프 방지
            }

            /** @var Node $current */
            $current = $stack->pop();

            if ($this->isSamePosition($current->position, $goal)) {
                return $this->reconstructPath($current);
            }

            $visited[$this->getPositionHash($current->position)] = true;

            foreach ($this->getNeighbors($current->position) as $neighbor) {
                $neighborHash = $this->getPositionHash($neighbor);
                if (!isset($visited[$neighborHash])) {
                    $stack->push(new Node($neighbor, $current));
                }
            }
        }

        return null;
    }

    /** ✅ 이동 가능한 이웃 노드 찾기 */
    private function getNeighbors(Vector3 $position): array {
        $neighbors = [];
        $directions = [
            new Vector3(1, 0, 0), new Vector3(-1, 0, 0),
            new Vector3(0, 0, 1), new Vector3(0, 0, -1)
        ];

        foreach ($directions as $dir) {
            $neighbor = $position->addVector($dir);
            if ($this->isWalkable($neighbor)) {
                $neighbors[] = $neighbor;
            }
        }

        return $neighbors;
    }

    /** ✅ 특정 블록이 이동 가능한지 확인 */
    private function isWalkable(Vector3 $position): bool {
        return true; // TODO: 월드에서 블록 이동 가능 여부 검사 추가
    }

    /** ✅ 두 노드 간 거리 계산 */
    private function distance(Vector3 $a, Vector3 $b): float {
        return sqrt(($a->x - $b->x) ** 2 + ($a->z - $b->z) ** 2);
    }

    /** ✅ A* 알고리즘에서 휴리스틱 계산 (맨해튼 거리) */
    private function heuristic(Vector3 $a, Vector3 $b): float {
        return abs($a->x - $b->x) + abs($a->z - $b->z);
    }

    /** ✅ 최종 경로 재구성 */
    private function reconstructPath(Node $node): array {
        $path = [];
        while ($node !== null) {
            $path[] = $node->position;
            $node = $node->cameFrom;
        }
        return array_reverse(array_slice($path, 0, self::MAX_PATH_LENGTH)); // ✅ 경로 최대 길이 제한
    }

    /** ✅ 위치 비교 (좌표 정규화) */
    private function isSamePosition(Vector3 $a, Vector3 $b): bool {
        return (int) floor($a->x) === (int) floor($b->x) &&
               (int) floor($a->z) === (int) floor($b->z);
    }

    /** ✅ 해시 키 생성 (빠른 비교) */
    private function getPositionHash(Vector3 $pos): string {
        return floor($pos->x) . ":" . floor($pos->z);
    }
}

class Node {
    public Vector3 $position;
    public ?Node $cameFrom;
    public float $gCost;
    public float $hCost;
    public function __construct(Vector3 $position, ?Node $cameFrom = null, float $gCost = 0, float $hCost = 0) {
        $this->position = clone $position;
        $this->cameFrom = $cameFrom;
        $this->gCost = $gCost;
        $this->hCost = $hCost;
    }
    public function fCost(): float {
        return $this->gCost + $this->hCost;
    }
}
