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

수정된 부분:
 * $plugin과 $entityAI 캡처: findPathAsync 함수 내에서 $plugin 인스턴스와 $this (EntityAI 인스턴스)를 로컬 변수에 저장합니다.
 * 생성자에 캡처한 변수 전달: 캡처한 $entityAI와 $plugin 변수를 익명 클래스의 생성자에 인자로 전달합니다.
 * 생성자에서 속성에 할당: 익명 클래스 내에 $entityAI와 $plugin 속성을 추가하고, 생성자에서 전달받은 인스턴스를 이 속성들에 할당합니다.
 * onRun() 함수 추가: 비동기 작업이 실제로 실행될 때 수행할 작업을 정의하는 onRun() 함수를 추가했습니다.
 * onCompletion() 함수 수정: 캡처한 $plugin 인스턴스를 사용하여 로그를 출력하는 부분을 추가했습니다.
핵심:
 * AsyncTask 내에서 $this를 직접 사용할 수 없기 때문에, 필요한 데이터를 캡처하여 생성자를 통해 AsyncTask에 전달해야 합니다.
 * onRun() 함수는 비동기 작업의 핵심 로직을 담당하며, onCompletion() 함수는 비동기 작업 완료 후 처리해야 할 작업을 담당합니다.
참고:
 * Pathfinder 클래스는 별도의 파일에 구현되어 있어야 합니다.
 * MobAITask 클래스에서 EntityAI 객체를 생성할 때, Main 클래스의 인스턴스 ($this)를 EntityAI 생성자에 전달해야 합니다.


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
