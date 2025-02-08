<?php

namespace HybridMobAI;

use pocketmine\math\Vector3;
use pocketmine\world\World;
use pocketmine\entity\Living;
use pocketmine\block\Block;

class EntityAI {
    private bool $enabled = false; // AI 활성화 여부
    private array $path = []; // A* 경로
    private ?Vector3 $target = null; // 목표 위치
    private array $entityPaths = [];
    
    public function setEnabled(bool $enabled): void {
        $this->enabled = $enabled;
    }

    public function isEnabled(): bool {
        return $this->enabled;
    }

    public function findPath(World $world, Vector3 $start, Vector3 $goal, string $algorithm): ?array {
    $pathfinder = new Pathfinder($world);

    switch ($algorithm) {
        case "A*":
            return $pathfinder->findPathAStar($start, $goal);
        case "Dijkstra":
            return $pathfinder->findPathDijkstra($start, $goal);
        case "Greedy":
            return $pathfinder->findPathGreedy($start, $goal);
        case "BFS":
            return $pathfinder->findPathBFS($start, $goal);
        case "DFS":
            return $pathfinder->findPathDFS($start, $goal);
        default:
            return null;
    }
}
    private function logDebug(string $message, mixed $data = null): void {
    $logMessage = "[" . date("Y-m-d H:i:s") . "] " . $message;
    if ($data !== null) {
        $logMessage .= " " . print_r($data, true);
    }
    $logMessage .= "\n";
    file_put_contents("debug_log.txt", $logMessage, FILE_APPEND);
}

public function findPathAsync(World $world, Vector3 $start, Vector3 $goal, string $algorithm, callable $callback): void {
    // ✅ 강제 변환 및 디버그 로그 추가
    if (!$start instanceof Vector3) {
        $this->logDebug("⚠️ findPathAsync - 변환 전 Start 값 (Position 객체 감지)", json_encode($start));
        
        // ✅ float 변환을 확실히 수행
        $start = new Vector3((float)$start->getX(), (float)$start->getY(), (float)$start->getZ());

        $this->logDebug("✅ findPathAsync - 변환 후 Start 값 (Vector3 변환 완료)", json_encode($start));
    }

    if (!$goal instanceof Vector3) {
        $this->logDebug("⚠️ findPathAsync - 변환 전 Goal 값 (Position 객체 감지)", json_encode($goal));
        
        // ✅ float 변환을 확실히 수행
        $goal = new Vector3((float)$goal->getX(), (float)$goal->getY(), (float)$goal->getZ());

        $this->logDebug("✅ findPathAsync - 변환 후 Goal 값 (Vector3 변환 완료)", json_encode($goal));
    }

    // ✅ 숫자가 맞는지 체크
    if (!is_numeric($start->x) || !is_numeric($start->y) || !is_numeric($start->z)) {
        throw new \InvalidArgumentException("findPathAsync: Start 좌표가 숫자가 아닙니다: " . json_encode($start));
    }
    if (!is_numeric($goal->x) || !is_numeric($goal->y) || !is_numeric($goal->z)) {
        throw new \InvalidArgumentException("findPathAsync: Goal 좌표가 숫자가 아닙니다: " . json_encode($goal));
    }

    $this->logDebug("🛠️ PathFinderTask 실행 준비 - Start: " . json_encode($start));
    $this->logDebug("🛠️ PathFinderTask 실행 준비 - Goal: " . json_encode($goal));

    try {
        $task = new PathfinderTask($world->getFolderName(), $start, $goal, $algorithm);
        Server::getInstance()->getAsyncPool()->submitTask($task);
    } catch (\Throwable $e) {
        $this->logDebug("❌ PathFinderTask 생성 중 오류 발생", $e->getMessage());
    }
}
public function setPath(Living $mob, array $path): void {
    $this->entityPaths[$mob->getId()] = $path;
}

public function hasPath(Living $mob): bool {
    return isset($this->entityPaths[$mob->getId()]);
}

public function moveAlongPath(Living $mob): void {
    if (!isset($this->entityPaths[$mob->getId()]) || empty($this->entityPaths[$mob->getId()])) {
        return;
    }

    $nextPosition = array_shift($this->entityPaths[$mob->getId()]);
    if ($nextPosition instanceof Vector3) {
        $mob->setMotion($nextPosition->subtractVector($mob->getPosition())->normalize()->multiply(0.2));
        $mob->lookAt($nextPosition);
    }
}
}
