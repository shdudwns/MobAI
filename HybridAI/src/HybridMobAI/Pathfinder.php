<?php

namespace HybridMobAI;

use pocketmine\math\Vector3;
use pocketmine\world\World;

class Pathfinder {
    private int $maxPathLength = 100; 
    private static function vectorToStr(Vector3 $vector): string {
        return "{$vector->x}:{$vector->y}:{$vector->z}";
    }

    public function findPathAStar(World $world, Vector3 $start, Vector3 $goal): ?array {
    $openSet = new \SplPriorityQueue();
    $openSet->insert($start, 0);

    $cameFrom = [];
    $gScore = [self::vectorToStr($start) => 0];
    $fScore = [self::vectorToStr($start) => $this->heuristic($start, $goal)];
    $visitedNodes = 0; // 탐색한 노드 개수

    while (!$openSet->isEmpty() && $visitedNodes < $this->maxPathLength) {
        $current = $openSet->extract();
        $visitedNodes++;
        if ($current->equals($goal)) {
            return $this->reconstructPath($cameFrom, $current);
        }

        foreach ($this->getNeighbors($world, $current) as $neighbor) {
            $neighborKey = self::vectorToStr($neighbor);
            $tentativeGScore = $gScore[self::vectorToStr($current)] + 1;

            if (!isset($gScore[$neighborKey]) || $tentativeGScore < $gScore[$neighborKey]) {
                $cameFrom[$neighborKey] = $current;
                $gScore[$neighborKey] = $tentativeGScore;
                $fScore[$neighborKey] = $gScore[$neighborKey] + $this->heuristic($neighbor, $goal);
                $openSet->insert($neighbor, -$fScore[$neighborKey]);
            }
        }
    }
    return null; // 최대 탐색 노드 수 초과 시 경로 중단
}
    
    public function findPathDijkstra(World $world, Vector3 $start, Vector3 $goal): ?array {
    $openSet = new \SplPriorityQueue();
    $openSet->insert($start, 0);

    $cameFrom = [];
    $cost = [self::vectorToStr($start) => 0];

    while (!$openSet->isEmpty()) {
        $current = $openSet->extract();
        $currentKey = self::vectorToStr($current);

        if ($current->equals($goal)) {
            return $this->reconstructPath($cameFrom, $current);
        }

        foreach ($this->getNeighbors($world, $current) as $neighbor) {
            $neighborKey = self::vectorToStr($neighbor);
            $newCost = $cost[$currentKey] + 1;

            if (!isset($cost[$neighborKey]) || $newCost < $cost[$neighborKey]) {
                $cameFrom[$neighborKey] = $current;
                $cost[$neighborKey] = $newCost;
                $openSet->insert($neighbor, -$newCost);
            }
        }
    }
    return null;
}
    
    public function findPathGreedy(World $world, Vector3 $start, Vector3 $goal): ?array {
    $openSet = new \SplPriorityQueue();
    $openSet->insert($start, -$this->heuristic($start, $goal));

    $cameFrom = [];

    while (!$openSet->isEmpty()) {
        $current = $openSet->extract();
        if ($current->equals($goal)) {
            return $this->reconstructPath($cameFrom, $current);
        }

        foreach ($this->getNeighbors($world, $current) as $neighbor) {
            $cameFrom[self::vectorToStr($neighbor)] = $current;
            $openSet->insert($neighbor, -$this->heuristic($neighbor, $goal));
        }
    }
    return null;
}

    public function findPathBFS(World $world, Vector3 $start, Vector3 $goal): ?array {
        $queue = [[$start]];
        $visited = [self::vectorToStr($start) => true];

        while (!empty($queue)) {
            $path = array_shift($queue);
            $current = end($path);

            if ($current->equals($goal)) {
                return $path;
            }

            foreach ($this->getNeighbors($world, $current) as $neighbor) {
                $neighborKey = self::vectorToStr($neighbor);
                if (!isset($visited[$neighborKey])) {
                    $visited[$neighborKey] = true;
                    $newPath = $path;
                    $newPath[] = $neighbor;
                    $queue[] = $newPath;
                }
            }
        }
        return null;
    }

    public function findPathDFS(World $world, Vector3 $start, Vector3 $goal): ?array {
        $stack = [[$start]];
        $visited = [self::vectorToStr($start) => true];

        while (!empty($stack)) {
            $path = array_pop($stack);
            $current = end($path);

            if ($current->equals($goal)) {
                return $path;
            }

            foreach ($this->getNeighbors($world, $current) as $neighbor) {
                $neighborKey = self::vectorToStr($neighbor);
                if (!isset($visited[$neighborKey])) {
                    $visited[$neighborKey] = true;
                    $newPath = $path;
                    $newPath[] = $neighbor;
                    $stack[] = $newPath;
                }
            }
        }
        return null;
    }

    private function heuristic(Vector3 $a, Vector3 $b): float {
    $dx = abs($a->x - $b->x);
    $dy = abs($a->y - $b->y);
    $dz = abs($a->z - $b->z);
    return sqrt($dx * $dx + $dy * $dy + $dz * $dz); // 유클리드 거리 계산
    }

    private function reconstructPath(array $cameFrom, Vector3 $current): array {
        $path = [$current];
        $currentKey = self::vectorToStr($current);
        while (isset($cameFrom[$currentKey])) {
            $current = $cameFrom[$currentKey];
            array_unshift($path, $current);
            $currentKey = self::vectorToStr($current);
        }
        return $path;
    }

    private function getNeighbors(World $world, Vector3 $pos): iterable {
        $directions = [
            new Vector3(1, 0, 0), new Vector3(-1, 0, 0),
            new Vector3(0, 0, 1), new Vector3(0, 0, -1),
            new Vector3(0, 1, 0), // 위로 1칸
            new Vector3(0, -1, 0), // 아래로 1칸

            // 대각선 (x, z)
            new Vector3(1, 0, 1), new Vector3(1, 0, -1),
            new Vector3(-1, 0, 1), new Vector3(-1, 0, -1),

            // 대각선 (x, y) - 경사면 이동 고려
            new Vector3(1, 1, 0), new Vector3(-1, 1, 0),
            new Vector3(1, -1, 0), new Vector3(-1, -1, 0),

            // 대각선 (y, z) - 경사면 이동 고려
            new Vector3(0, 1, 1), new Vector3(0, -1, 1),
            new Vector3(0, 1, -1), new Vector3(0, -1, -1),

            // 대각선 (x, y, z) - 3차원 대각선
            new Vector3(1, 1, 1), new Vector3(1, 1, -1), new Vector3(1, -1, 1), new Vector3(1, -1, -1),
            new Vector3(-1, 1, 1), new Vector3(-1, 1, -1), new Vector3(-1, -1, 1), new Vector3(-1, -1, -1),

        ];

        foreach ($directions as $dir) {
            $neighbor = $pos->addVector($dir);
            $x = (int)$neighbor->x;
            $y = (int)$neighbor->y;
            $z = (int)$neighbor->z;

            $block = $world->getBlockAt($x, $y, $z);
            $blockBelow = $world->getBlockAt($x, $y - 1, $z);


            if ($dir->y === 0) { // 수평 이동
                if (!$block->isSolid() && $blockBelow->isSolid()) {
                    yield $neighbor;
                }
            } elseif ($dir->y === 1) { // 위로 이동
                $blockAbove = $world->getBlockAt($x, $y + 1, $z); // 추가
                if (!$block->isSolid() && !$blockAbove->isSolid()) { // 위, 아래 블럭이 비어있어야함
                    yield $neighbor;
                }
            } elseif ($dir->y === -1) { // 아래로 이동
                if (!$blockBelow->isSolid()) { // 아래 블럭이 비어있어야함
                    yield $neighbor;
                }
            } else { // 대각선 이동
                $block1 = $world->getBlockAt($x, $y, $z); // 대각선 위치 블럭
                $block2 = $world->getBlockAt($x, $y-1, $z); // 대각선 아래 블럭
                if(!$block1->isSolid() && $block2->isSolid()){ //대각선 위치 블럭이 비어있고, 대각선 아래 블럭이 solid여야함.
                    yield $neighbor;
                }
            }
        }
    }
}
