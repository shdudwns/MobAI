<?php

namespace HybridMobAI;

use pocketmine\math\Vector3;
use pocketmine\world\World;
use pocketmine\entity\Living;
use pocketmine\block\Block;
use pocketmine\Server;
use pocketmine\scheduler\AsyncTask;
use pocketmine\world\Position;
use pocketmine\plugin\PluginBase;

class EntityAI {
    private bool $enabled = false;
    private array $path = [];
    private ?Vector3 $target = null;
    private array $entityPaths = [];
    private PluginBase $plugin;

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

        $plugin = $this->plugin;
        $entityAI = $this;

        // 1. 캡처할 모든 데이터를 이 곳에서 캡처합니다.
        $capturedThis = $this;
        $capturedWorld = $world;
        $capturedStart = $start;
        $capturedGoal = $goal;
        $capturedAlgorithm = $algorithm;

        // 2. 콜백 함수를 래핑하는 *일반 함수*를 만듭니다. (익명 함수가 아님)
        $callbackFunction = function($result) use ($capturedThis, $capturedWorld, $capturedStart, $capturedGoal, $capturedAlgorithm, $callback) {
            $callback($result, $capturedThis, $capturedWorld, $capturedStart, $capturedGoal, $capturedAlgorithm);
        };

        // 3. AsyncTask 객체 생성을 별도의 함수로 분리 (핵심 변경 사항)
        $createAsyncTask = function() use ($worldName, $startX, $startY, $startZ, $goalX, $goalY, $goalZ, $algorithm, $callbackFunction, $entityAI, $plugin) {
            return new class($worldName, $startX, $startY, $startZ, $goalX, $goalY, $goalZ, $algorithm, $callbackFunction, $entityAI, $plugin) extends AsyncTask {
                // ... (AsyncTask 속성들 - 이전과 동일)

                public function __construct(string $worldName, float $startX, float $startY, float $startZ, float $goalX, float $goalY, float $goalZ, string $algorithm, callable $callbackFunction, EntityAI $entityAI, PluginBase $plugin) {
                    // ... (AsyncTask 생성자 - 이전과 동일)
                    $this->callbackFunction = $callbackFunction;
                }

                public function onRun(): void {
                    $world = Server::getInstance()->getWorldManager()->getWorldByName($this->worldName);
                    if (!$world instanceof World) {
                        $this->setResult(null);
                        return;
                    }

                    $start = new Vector3($this->startX, $this->startY, $this->startZ);
                    $goal = new Vector3($this->goalX, $this->goalY, $this->goalZ);

                    // PathfinderTask에 필요한 모든 좌표값을 float으로 형변환하여 전달
                    $startX = (float) $start->x;
                    $startY = (float) $start->y;
                    $startZ = (float) $start->z;
                    $goalX = (float) $goal->x;
                    $goalY = (float) $goal->y;
                    $goalZ = (float) $goal->z;

                    $pathfinderTask = new PathfinderTask($this->worldName, $startX, $startY, $startZ, $goalX, $goalY, $goalZ, $this->algorithm);
                    $path = $pathfinderTask->findPath();

                    $this->setResult($path);

                }

                public function onCompletion(): void {
                    $result = $this->getResult();
                    ($this->callbackFunction)($result); // 래핑된 콜백 호출
                    // ... (로그 출력 - 이전과 동일)
                }
            };
        };

        // 4. AsyncTask 실행
        Server::getInstance()->getAsyncPool()->submitTask($createAsyncTask()); // 함수 호출 후 반환된 AsyncTask 객체 전달
    }
    public function findPath(World $world, Vector3 $start, Vector3 $goal, string $algorithm): ?array {
        $pathfinder = new Pathfinder();

        switch ($algorithm) {
            case "A*":
                return $pathfinder->findPathAStar($world, $start, $goal);
            case "Dijkstra":
                return $pathfinder->findPathDijkstra($world, $start, $goal);
            case "Greedy":
                return $pathfinder->findPathGreedy($world, $start, $goal);
            case "BFS":
                return $pathfinder->findPathBFS($world, $start, $goal);
            case "DFS":
                return $pathfinder->findPathDFS($world, $start, $goal);
            default:
                return null;
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
