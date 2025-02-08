<?php

namespace HybridMobAI;

use pocketmine\math\Vector3;
use pocketmine\world\World;

class Pathfinder {
    private static function vectorToStr(Vector3 $vector): string {
        return "{$vector->x}:{$vector->y}:{$vector->z}";
    }

    public function findPathAStar(World $world, Vector3 $start, Vector3 $goal): ?array {
    $openSet = [$start];
    $openSetHash = [self::vectorToStr($start) => true];
    $cameFrom = [];
    $gScore = [self::vectorToStr($start) => 0];
    $fScore = [self::vectorToStr($start) => $this->heuristic($start, $goal)];

    $maxDepth = 500;
    $depth = 0;

    while (!empty($openSet) && $depth < $maxDepth) {
        // ✅ 기존 usort() 대신 array_multisort() 사용하여 정렬 최적화
        $keys = array_map(fn($vec) => $fScore[self::vectorToStr($vec)], $openSet);
        array_multisort($keys, SORT_ASC, $openSet);

        $current = array_shift($openSet);
        $currentKey = self::vectorToStr($current);

        if ($current->equals($goal)) {
            return $this->reconstructPath($cameFrom, $current);
        }

        foreach ($this->getNeighbors($world, $current) as $neighbor) {
            $neighborKey = self::vectorToStr($neighbor);
            $tentativeGScore = $gScore[$currentKey] + 1;

            // ✅ `in_array()` 대신 `isset()` 사용하여 탐색 속도 개선
            if (!isset($gScore[$neighborKey]) || $tentativeGScore < $gScore[$neighborKey]) {
                $cameFrom[$neighborKey] = $current;
                $gScore[$neighborKey] = $tentativeGScore;
                $fScore[$neighborKey] = $gScore[$neighborKey] + $this->heuristic($neighbor, $goal);

                if (!isset($openSetHash[$neighborKey])) {
                    $openSet[] = $neighbor;
                    $openSetHash[$neighborKey] = true;
                }
            }
        }

        $depth++;
    }

    return null;
}
    
    public function findPathDijkstra(World $world, Vector3 $start, Vector3 $goal): ?array {
        $openSet = [$start];
        $cameFrom = [];
        $cost = [self::vectorToStr($start) => 0];

        while (!empty($openSet)) {
            usort($openSet, fn($a, $b) => $cost[self::vectorToStr($a)] <=> $cost[self::vectorToStr($b)]);
            $current = array_shift($openSet);
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
                    $openSet[] = $neighbor;
                }
            }
        }
        return null;
    }

    public function findPathGreedy(World $world, Vector3 $start, Vector3 $goal): ?array {
        $current = $start;
        $path = [];

        while (!$current->equals($goal)) {
            $neighbors = $this->getNeighbors($world, $current);
            usort($neighbors, fn($a, $b) => $this->heuristic($a, $goal) <=> $this->heuristic($b, $goal));

            if (empty($neighbors)) return null;

            $current = array_shift($neighbors);
            $path[] = $current;
        }

        return $path;
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
        return abs($a->x - $b->x) + abs($a->y - $b->y) + abs($a->z - $b->z);
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

    private function getNeighbors(World $world, Vector3 $pos): array {
    $neighbors = [];
    $directions = [
        new Vector3(1, 0, 0), new Vector3(-1, 0, 0),
        new Vector3(0, 0, 1), new Vector3(0, 0, -1)
    ];

    foreach ($directions as $dir) {
        $neighbor = $pos->addVector($dir);
        $block = $world->getBlockAt((int)$neighbor->x, (int)$neighbor->y, (int)$neighbor->z);
        $blockBelow = $world->getBlockAt((int)$neighbor->x, (int)($neighbor->y - 1), (int)$neighbor->z);

        // ✅ 기존 방식: 바닥이 solid하고 위에 장애물이 없으면 이동 가능
        if (!$block->isSolid() && $blockBelow->isSolid()) {
            $neighbors[] = $neighbor;
        }

        // ✅ 추가: 점프할 수 있는 블록 탐색 (최대 2칸 높이)
        for ($i = 1; $i <= 2; $i++) {
            $jumpPos = $neighbor->addVector(0, $i, 0);
            $jumpBlock = $world->getBlockAt((int)$jumpPos->x, (int)$jumpPos->y, (int)$jumpPos->z);
            $jumpBlockBelow = $world->getBlockAt((int)$jumpPos->x, (int)($jumpPos->y - 1), (int)$jumpPos->z);

            if (!$jumpBlock->isSolid() && $jumpBlockBelow->isSolid()) {
                $neighbors[] = $jumpPos;
                break; // 한 번만 점프 가능
            }
        }
    }

    return $neighbors;
}
}
