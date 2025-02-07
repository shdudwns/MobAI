<?php

namespace HybridMobAI;

use pocketmine\math\Vector3;
use pocketmine\world\World;
use pocketmine\block\Block;

class Pathfinder {
    private World $world;

    public function __construct(World $world) {
        $this->world = $world;
    }

    public function findPathAStar(Vector3 $start, Vector3 $goal): ?array {
    $openSet = [$start];
    $cameFrom = [];
    $gScore = [self::vectorToStr($start) => 0];
    $fScore = [self::vectorToStr($start) => $this->heuristic($start, $goal)];

    $maxDepth = 500; // 탐색 깊이 제한
    $depth = 0;

    while (!empty($openSet) && $depth < $maxDepth) {
        usort($openSet, fn($a, $b) => $fScore[self::vectorToStr($a)] <=> $fScore[self::vectorToStr($b)]);
        $current = array_shift($openSet);
        $currentKey = self::vectorToStr($current);

        if ($current->equals($goal)) {
            return $this->reconstructPath($cameFrom, $current);
        }

        foreach ($this->getNeighbors($current) as $neighbor) {
            $neighborKey = self::vectorToStr($neighbor);
            $tentativeGScore = $gScore[$currentKey] + 1;

            if (!isset($gScore[$neighborKey]) || $tentativeGScore < $gScore[$neighborKey]) {
                $cameFrom[$neighborKey] = $current;
                $gScore[$neighborKey] = $tentativeGScore;
                $fScore[$neighborKey] = $gScore[$neighborKey] + $this->heuristic($neighbor, $goal);

                if (!in_array($neighbor, $openSet, true)) {
                    $openSet[] = $neighbor;
                }
            }
        }

        $depth++;
    }

    return null; // 최적 경로를 찾지 못한 경우
}

public function findPathDijkstra(Vector3 $start, Vector3 $goal): ?array {
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

        foreach ($this->getNeighbors($current) as $neighbor) {
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

public function findPathGreedy(Vector3 $start, Vector3 $goal): ?array {
    $current = $start;
    $path = [];

    while (!$current->equals($goal)) {
        $neighbors = $this->getNeighbors($current);
        usort($neighbors, fn($a, $b) => $this->heuristic($a, $goal) <=> $this->heuristic($b, $goal));

        if (empty($neighbors)) return null;

        $current = array_shift($neighbors);
        $path[] = $current;
    }

    return $path;
}
    public function findPathBFS(Vector3 $start, Vector3 $goal): ?array {
    $queue = [[$start]];
    $visited = [self::vectorToStr($start) => true];

    while (!empty($queue)) {
        $path = array_shift($queue);
        $current = end($path);

        if ($current->equals($goal)) {
            return $path;
        }

        foreach ($this->getNeighbors($current) as $neighbor) {
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

public function findPathDFS(Vector3 $start, Vector3 $goal): ?array {
    $stack = [[$start]];
    $visited = [self::vectorToStr($start) => true];

    while (!empty($stack)) {
        $path = array_pop($stack);
        $current = end($path);

        if ($current->equals($goal)) {
            return $path;
        }

        foreach ($this->getNeighbors($current) as $neighbor) {
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
private static function vectorToStr(Vector3 $vector): string {
    return "{$vector->x}:{$vector->y}:{$vector->z}";
}

    private function heuristic(Vector3 $a, Vector3 $b): float {
        return abs($a->x - $b->x) + abs($a->y - $b->y) + abs($a->z - $b->z);
    }

    private function reconstructPath(array $cameFrom, Vector3 $current): array {
        $path = [$current];

        while (isset($cameFrom[spl_object_hash($current)])) {
            $current = $cameFrom[spl_object_hash($current)];
            array_unshift($path, $current);
        }

        return $path;
    }

    private function getNeighbors(Vector3 $pos): array {
        $neighbors = [];
        $directions = [
            new Vector3(1, 0, 0), new Vector3(-1, 0, 0),
            new Vector3(0, 0, 1), new Vector3(0, 0, -1)
        ];

        foreach ($directions as $dir) {
            $neighbor = $pos->addVector($dir);
            $block = $this->world->getBlockAt((int)$neighbor->x, (int)$neighbor->y, (int)$neighbor->z);

            if (!$block->isSolid()) {
                $neighbors[] = $neighbor;
            }
        }

        return $neighbors;
    }
}
