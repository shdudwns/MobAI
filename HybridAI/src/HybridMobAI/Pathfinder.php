<?php

namespace HybridMobAI;

use pocketmine\math\Vector3;
use pocketmine\world\World;
use pocketmine\block\Block;
use pocketmine\scheduler\AsyncTask;

class Pathfinder {
    private World $world;

    public function __construct(World $world) {
        $this->world = $world;
    }

    public function findPathAStar(Vector3 $start, Vector3 $goal): ?array {
    $openSet = [$start];
    $openSetHash = [self::vectorToStr($start) => true];
    $cameFrom = [];
    $gScore = [self::vectorToStr($start) => 0];
    $fScore = [self::vectorToStr($start) => $this->heuristic($start, $goal)];

    $maxDepth = 500;
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
                    $openSetHash[$neighborKey] = true;
                }
            }
        }

        $depth++;
    }

    return null;
}

private static function vectorToStr(Vector3 $vector): string {
    return "{$vector->x}:{$vector->y}:{$vector->z}";
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

    private function getNeighbors(Vector3 $pos): array {
    $neighbors = [];
    $directions = [
        new Vector3(1, 0, 0), new Vector3(-1, 0, 0),
        new Vector3(0, 0, 1), new Vector3(0, 0, -1)
    ];

    foreach ($directions as $dir) {
        $neighbor = $pos->addVector($dir);
        $block = $this->world->getBlockAt((int)$neighbor->x, (int)$neighbor->y, (int)$neighbor->z);
        $blockBelow = $this->world->getBlockAt((int)$neighbor->x, (int)($neighbor->y - 1), (int)$neighbor->z);

        if (!$block->isSolid() && $blockBelow->isSolid()) {
            $neighbors[] = $neighbor;
        }
    }

    return $neighbors;
}
}

class PathfinderTask extends AsyncTask {
    private string $worldName;
    private float $startX, $startY, $startZ;
    private float $goalX, $goalY, $goalZ;
    private string $algorithm;

    public function __construct(string $worldName, Vector3 $start, Vector3 $goal, string $algorithm) {
    $this->worldName = $worldName;

    // ✅ 만약 Position이 들어오면 예외 발생
    if ($start instanceof Position || $goal instanceof Position) {
        throw new \InvalidArgumentException("PathfinderTask: Position 객체가 전달됨! Vector3로 변환 필요. start: " . json_encode($start) . " goal: " . json_encode($goal));
    }

    // ✅ 숫자 확인
    foreach (['x', 'y', 'z'] as $key) {
        if (!is_numeric($start->{$key}) || !is_numeric($goal->{$key})) {
            throw new \InvalidArgumentException("PathfinderTask: 좌표 값이 숫자가 아닙니다. start: " . json_encode($start) . " goal: " . json_encode($goal));
        }
    }

    $this->startX = (float) $start->x;
    $this->startY = (float) $start->y;
    $this->startZ = (float) $start->z;
    $this->goalX = (float) $goal->x;
    $this->goalY = (float) $goal->y;
    $this->goalZ = (float) $goal->z;
    $this->algorithm = $algorithm;
}

    private function logDebug(string $message, mixed $data = null): void {
        $logMessage = "[" . date("Y-m-d H:i:s") . "] " . $message;
        if ($data !== null) {
            $logMessage .= " " . print_r($data, true);
        }
        $logMessage .= "\n";
        file_put_contents("debug_log.txt", $logMessage, FILE_APPEND);
    }

    public function onRun(): void {
        $start = new Vector3($this->startX, $this->startY, $this->startZ);
        $goal = new Vector3($this->goalX, $this->goalY, $this->goalZ);

        $pathfinder = new Pathfinder();
        $path = match ($this->algorithm) {
            "A*" => $pathfinder->findPathAStar($start, $goal),
            "BFS" => $pathfinder->findPathBFS($start, $goal),
            "DFS" => $pathfinder->findPathDFS($start, $goal),
            "Dijkstra" => $pathfinder->findPathDijkstra($start, $goal),
            "Greedy" => $pathfinder->findPathGreedy($start, $goal),
            default => null
        };

        $this->setResult($path);
    }
}
