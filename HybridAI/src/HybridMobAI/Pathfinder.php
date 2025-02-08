<?php

namespace HybridMobAI;

use pocketmine\math\Vector3;
use pocketmine\world\World;

class Pathfinder {
    private static function vectorToStr(Vector3 $vector): string {
        return "{$vector->x}:{$vector->y}:{$vector->z}";
    }

    public function findPathAStar(World $world, Vector3 $start, Vector3 $goal): ?array {
    $openSet = new \SplPriorityQueue();
    $openSet->insert($start, 0);

    $cameFrom = [];
    $gScore = [self::vectorToStr($start) => 0];
    $fScore = [self::vectorToStr($start) => $this->heuristic($start, $goal)];

    while (!$openSet->isEmpty()) {
        $current = $openSet->extract();
        $currentKey = self::vectorToStr($current);

        if ($current->equals($goal)) {
            return $this->reconstructPath($cameFrom, $current);
        }

        foreach ($this->getNeighbors($world, $current) as $neighbor) {
            $neighborKey = self::vectorToStr($neighbor);
            $tentativeGScore = $gScore[$currentKey] + 1;

            if (!isset($gScore[$neighborKey]) || $tentativeGScore < $gScore[$neighborKey]) {
                $cameFrom[$neighborKey] = $current;
                $gScore[$neighborKey] = $tentativeGScore;
                $fScore[$neighborKey] = $gScore[$neighborKey] + $this->heuristic($neighbor, $goal);
                $openSet->insert($neighbor, -$fScore[$neighborKey]); // 우선순위 큐에서 낮은 값이 높은 우선순위를 가짐
            }
        }
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
        new Vector3(0, 0, 1), new Vector3(0, 0, -1)
    ];

    foreach ($directions as $dir) {
        $neighbor = $pos->addVector($dir);
        $block = $world->getBlockAt((int)$neighbor->x, (int)$neighbor->y, (int)$neighbor->z);
        $blockBelow = $world->getBlockAt((int)$neighbor->x, (int)($neighbor->y - 1), (int)$neighbor->z);

        if (!$block->isSolid() && $blockBelow->isSolid()) {
            yield $neighbor; // ✅ 배열을 반환하지 않고 `yield` 사용하여 성능 최적화
        }
    }
}
}
