<?php

namespace HybridMobAI;

use pocketmine\math\Vector3;
use pocketmine\world\World;
use pocketmine\Server;
use pocketmine\block\Block;
use pocketmine\block\Air;

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
        [1, 0, 0], [-1, 0, 0], [0, 0, 1], [0, 0, -1], // 기본 수평 이동
        [1, 1, 0], [-1, 1, 0], [0, 1, 1], [0, 1, -1], // 점프 가능 여부 확인
        [1, -1, 0], [-1, -1, 0], [0, -1, 1], [0, -1, -1] // 내려가기 가능 여부 확인
    ];

    foreach ($directions as $dir) {
        $x = (int) $pos->x + $dir[0];
        $y = (int) $pos->y + $dir[1];
        $z = (int) $pos->z + $dir[2];

        $block = $world->getBlockAt($x, $y, $z);
        $blockBelow = $world->getBlockAt($x, $y - 1, $z);
        $blockAbove = $world->getBlockAt($x, $y + 1, $z);
        $blockAbove2 = $world->getBlockAt($x, $y + 2, $z);
        $blockAbove3 = $world->getBlockAt($x, $y + 3, $z); // ✅ 추가적인 머리 위 블록 검사

        // ✅ 이동 가능한 블록 체크 (공기가 아닌 경우만)
        if ($blockBelow === null || $this->isWalkableBlock($blockBelow)) {
            $neighbors[] = new Vector3($x, $y, $z);
        }

        // ✅ 머리 위 장애물 감지 예외 처리
        if ($this->isSolidBlock($blockAbove) || $this->isSolidBlock($blockAbove2) || $this->isSolidBlock($blockAbove3)) {
            if (!$blockAbove instanceof Grass && !$blockAbove2 instanceof Grass && !$blockAbove3 instanceof Grass) {
                Server::getInstance()->broadcastMessage("⛔ [AI] 머리 위 장애물 발견: {$blockAbove->getName()} at {$x}, {$y}, {$z}");
                continue;
            }
        }

        // ✅ 장애물 여부 판단 (isSolidBlock 체크)
        if ($this->isSolidBlock($block)) {
            Server::getInstance()->broadcastMessage("🚧 [AI] 장애물 감지 (이동 불가): {$block->getName()} at {$x}, {$y}, {$z}");
            continue;
        }

        // ✅ 이동 가능한 블록 추가
        $neighbors[] = new Vector3($x, $y, $z);
    }

    return $neighbors;
}
    
    private function isSolidBlock(Block $block): bool {
    $solidBlockNames = [
        "stone", "dirt", "cobblestone", "log", "planks", "brick", "sandstone",
        "obsidian", "bedrock", "iron_block", "gold_block", "diamond_block",
        "concrete", "concrete_powder", "netherrack", "end_stone", "deepslate"
    ];

    $blockName = strtolower($block->getName());

    return in_array($blockName, $solidBlockNames);
}

private function isWalkableBlock(Block $block): bool {
    $walkableBlockNames = [
        "grass", "gravel", "sand", "stair", "slab", "path", "carpet"
    ];

    $blockName = strtolower($block->getName());

    return in_array($blockName, $walkableBlockNames);
}
}
