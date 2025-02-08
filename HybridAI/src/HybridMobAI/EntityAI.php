<?php

namespace HybridMobAI;

use pocketmine\math\Vector3;
use pocketmine\world\World;
use pocketmine\entity\Living;
use pocketmine\block\Block;
use pocketmine\Server;
use pocketmine\scheduler\AsyncTask;
use pocketmine\world\Position;
use pocketmine\plugin\PluginBase; // PluginBase 임포트

class EntityAI {
    private bool $enabled = false; // AI 활성화 여부
    private array $path = []; // A* 경로
    private ?Vector3 $target = null; // 목표 위치
    private array $entityPaths = [];
    private PluginBase $plugin; // PluginBase 인스턴스 저장

    public function __construct(PluginBase $plugin) {
        $this->plugin = $plugin;
    }

    public function setEnabled(bool $enabled): void {
        $this->enabled = $enabled;
    }

    public function isEnabled(): bool {
        return $this->enabled;
    }

    public function findPathAsync(World $world, Position $start, Position $goal, string $algorithm, callable $callback): void {
    $worldName = $world->getFolderName();
    $startX = $start->x;
    $startY = $start->y;
    $startZ = $start->z;
    $goalX = $goal->x;
    $goalY = $goal->y;
    $goalZ = $goal->z;

    $plugin = $this->plugin; // $plugin 인스턴스 캡처
    $entityAI = $this;       // $this 캡처

    Server::getInstance()->getAsyncPool()->submitTask(new class($worldName, $startX, $startY, $startZ, $goalX, $goalY, $goalZ, $algorithm, $callback, $entityAI, $plugin) extends AsyncTask {
        private string $worldName;
        private float $startX;
        private float $startY;
        private float $startZ;
        private float $goalX;
        private float $goalY;
        private float $goalZ;
        private string $algorithm;
        private $callback;
        private EntityAI $entityAI;
        private PluginBase $plugin;

        public function __construct(string $worldName, float $startX, float $startY, float $startZ, float $goalX, float $goalY, float $goalZ, string $algorithm, callable $callback, EntityAI $entityAI, PluginBase $plugin) {
            $this->worldName = $worldName;
            $this->startX = $startX;
            $this->startY = $startY;
            $this->startZ = $startZ;
            $this->goalX = $goalX;
            $this->goalY = $goalY;
            $this->goalZ = $goalZ;
            $this->algorithm = $algorithm;
            $this->callback = $callback;
            $this->entityAI = $entityAI;
            $this->plugin = $plugin;
        }

        public function onRun(): void {
            $world = Server::getInstance()->getWorldManager()->getWorldByName($this->worldName);
            if ($world instanceof World) {
                $start = new Vector3($this->startX, $this->startY, $this->startZ);
                $goal = new Vector3($this->goalX, $this->goalY, $this->goalZ);
                $pathfinder = new Pathfinder($world);

                $path = match ($this->algorithm) {
                    "A*" => $pathfinder->findPathAStar($start, $goal),
                    "Dijkstra" => $pathfinder->findPathDijkstra($start, $goal),
                    "Greedy" => $pathfinder->findPathGreedy($start, $goal),
                    "BFS" => $pathfinder->findPathBFS($start, $goal),
                    "DFS" => $pathfinder->findPathDFS($start, $goal),
                    default => null,
                };

                $this->setResult($path);
            } else {
                $this->setResult(null);
            }
        }

        public function onCompletion(): void {
            ($this->callback)($this->getResult());
            $this->plugin->getLogger()->info("경로 탐색 완료!");
        }
    });
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
