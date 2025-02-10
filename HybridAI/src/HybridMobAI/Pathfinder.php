<?php

namespace HybridMobAI;

use pocketmine\math\Vector3;
use pocketmine\world\World;
use pocketmine\Server;

class Pathfinder {
    private int $maxPathLength = 500;
    private array $vectorPool = [];
    private static function vectorToStr(Vector3 $vector): string {
        return "{$vector->x}:{$vector->y}:{$vector->z}";
    }

    private function getVector(float $x, float $y, float $z): Vector3
    {
        $key = "$x:$y:$z";
        if (isset($this->vectorPool[$key])) {
            return $this->vectorPool[$key];
        }
        $vector = new Vector3($x, $y, $z);
        $this->vectorPool[$key] = $vector;
        return $vector;
    }

    public function findPathAStar(World $world, Vector3 $start, Vector3 $goal): ?array {
    $openSet = new \SplPriorityQueue();
    $openSet->insert($start, 0);

    $cameFrom = [];
    $gScore = [self::vectorToStr($start) => 0];
    $fScore = [self::vectorToStr($start) => $this->heuristic($start, $goal)];
    $visitedNodes = 0;

    Server::getInstance()->broadcastMessage("🔍 [AI] A* 탐색 시작: {$start->x}, {$start->y}, {$start->z} → {$goal->x}, {$goal->y}, {$goal->z}");

    while (!$openSet->isEmpty()) {
        if ($visitedNodes >= $this->maxPathLength) {
            Server::getInstance()->broadcastMessage("❌ [AI] A* 탐색 실패: 최대 탐색 노드 초과 ({$this->maxPathLength})");
            return null;
        }

        $current = $openSet->extract();
        $visitedNodes++;

        if ($current->equals($goal)) {
            Server::getInstance()->broadcastMessage("✅ [AI] 경로 발견! 노드 방문 수: {$visitedNodes}");
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

    Server::getInstance()->broadcastMessage("⚠️ [AI] A* 탐색 종료: 경로 없음 (노드 방문: {$visitedNodes})");
    return null;
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
    return ($dx + $dz) + ($dy * 2); // ✅ 높이(y) 차이에 더 높은 가중치 부여
}

    private function reconstructPath(array $cameFrom, Vector3 $current): array
    {
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
        [1, 0, 0], [-1, 0, 0], [0, 0, 1], [0, 0, -1], // 기본 이동 (수평)
        [0, 1, 0], [0, -1, 0]  // 위/아래 이동
    ];

    foreach ($directions as $dir) {
        $x = (int) $pos->x + $dir[0];
        $y = (int) $pos->y + $dir[1];
        $z = (int) $pos->z + $dir[2];

        // ✅ 현재 몬스터가 있는 위치는 검사에서 제외
        if ($x === (int) $pos->x && $y === (int) $pos->y && $z === (int) $pos->z) {
            continue;
        }

        $block = $world->getBlockAt($x, $y, $z);
        $blockBelow = $world->getBlockAt($x, $y - 1, $z);
        $blockAbove = $world->getBlockAt($x, $y + 1, $z);

        // ✅ 장애물 블록인지 확인
        if ($block->isSolid()) {
            Server::getInstance()->broadcastMessage("🚧 [AI] 장애물 감지 (이동 불가): {$block->getName()} at {$x}, {$y}, {$z}");
            continue;
        }

        // ✅ 공중 이동 불가 → 아래 블록이 Air인 경우 추가 검사
        if ($blockBelow instanceof Air) {
            $blockTwoBelow = $world->getBlockAt($x, $y - 2, $z);
            
            // ✅ 만약 블록이 2칸 아래까지 Air라면 이동 불가 (공중 떠 있음)
            if ($blockTwoBelow instanceof Air) {
                Server::getInstance()->broadcastMessage("⚠️ [AI] 공중 이동 불가: {$blockBelow->getName()} at {$x}, {$y}, {$z}");
                continue;
            }
        }

        // ✅ 머리 위 장애물 확인 (점프 가능 여부)
        if ($dir[1] === 1 && $blockAbove->isSolid()) {
            Server::getInstance()->broadcastMessage("⛔ [AI] 머리 위 장애물 발견: {$blockAbove->getName()} at {$x}, {$y}, {$z}");
            continue;
        }

        // ✅ 이동 가능한 블록으로 추가
        $neighbors[] = new Vector3($x, $y, $z);
    }

    Server::getInstance()->broadcastMessage("✅ [AI] 탐색 가능한 이웃 블록 수: " . count($neighbors));
    return $neighbors;
    }
}
