<?php

namespace HybridMobAI;

use pocketmine\scheduler\Task;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\entity\Living;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\math\VectorMath;
use pocketmine\block\{Air, Block, TallGrass};
use pocketmine\math\AxisAlignedBB as AABB;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\block\Stair;
use pocketmine\block\Slab;
use pocketmine\world\Position;

class MobAITask extends Task {
    private Main $plugin;
    private int $tickCounter = 0;
    private array $hasLanded = [];
    private array $landedTick = [];
    private int $changeDirectionTick = 0;
    private bool $aiEnabled;
    private EntityAI $entityAI;
    private array $algorithmPriority;
    private array $lastPathUpdate = [];

    public function __construct(Main $plugin, bool $aiEnabled, array $algorithmPriority) {
    $this->plugin = $plugin;
    $this->aiEnabled = $aiEnabled;
    $this->algorithmPriority = $algorithmPriority;
    $this->entityAI = new EntityAI($this->plugin, $this->aiEnabled);
    }

    public function onRun(): void {
        $this->tickCounter++;

        if ($this->tickCounter % 2 !== 0) return;

        foreach (Server::getInstance()->getWorldManager()->getWorlds() as $world) {
            foreach ($world->getEntities() as $entity) {
                if ($entity instanceof Zombie) {
                    $this->handleMobAI($entity);
                }
            }
        }
    }
private function handleMobAI(Living $mob): void {
    $tracker = new EntityTracker();
    $navigator = new EntityNavigator();
    $detector = new ObstacleDetector($this->plugin);
    $ai = new EntityAI($this->plugin, $this->aiEnabled);
    
    if (!$this->aiEnabled) {
        $nearestPlayer = $tracker->findNearestPlayer($mob);
        if ($nearestPlayer !== null) {
            $navigator->moveToPlayer($mob, $nearestPlayer, $this->aiEnabled);
        } else {
            $navigator->moveRandomly($mob);
        }
        $detector->checkForObstaclesAndJump($mob, $mob->getWorld());
        return;
    }

    $mobId = $mob->getId();
    $currentTick = Server::getInstance()->getTick();

    // ✅ 장애물 감지 및 점프를 가장 먼저 실행 (우선순위 상향)

    $player = $tracker->findNearestPlayer($mob);
    if ($mob->isClosed() || !$mob->isAlive()) {
        return; // 💀 몬스터가 죽었으면 AI 처리 중단
    }
    $ai->avoidObstacle($mob);
    $detector->checkForObstaclesAndJump($mob, $mob->getWorld());
    if ($player !== null) {
        $previousTarget = $ai->getTarget($mob);

        if ($previousTarget !== null && $previousTarget->distanceSquared($player->getPosition()) < 4) {
            $ai->moveAlongPath($mob);
            return;
        }

        $ai->setTarget($mob, $player->getPosition());

        if ($ai->hasPath($mob)) {
            $navigator->moveAlongPath($mob);
        } else {
            $navigator->moveToPlayer($mob, $player, $this->aiEnabled);
        }

        if (!isset($this->lastPathUpdate[$mobId]) || ($currentTick - $this->lastPathUpdate[$mobId] > 40)) {
            $this->lastPathUpdate[$mobId] = $currentTick;
            $algorithm = $this->selectBestAlgorithm($mob, $player);
            $ai->findPathAsync(
                $mob->getWorld(),
                $mob->getPosition(),
                $player->getPosition(),
                $algorithm,
                function (?array $path) use ($mob, $ai, $navigator) {
                    if ($path !== null) {
                        $ai->setPath($mob, $path);
                        $ai->moveAlongPath($mob);
                    } else {
                        Server::getInstance()->broadcastMessage("⚠️ [AI] 경로 없음");
                    }
                }
            );
        }
    }
}

    private function selectBestAlgorithm(Living $mob, Player $player): string {
    $distance = $mob->getPosition()->distance($player->getPosition());
    $enabledAlgorithms = $this->plugin->getConfig()->get("AI")["pathfindingg_priority"] ?? ["A*"];

    if ($distance > 30 && in_array("Dijkstra", $enabledAlgorithms)) {
        return "Dijkstra"; // ✅ 먼 거리일 때 최적 알고리즘
    } elseif ($distance < 10 && in_array("A*", $enabledAlgorithms)) {
        return "A*"; // ✅ 가까운 거리일 때 A* 사용
    } elseif (in_array("BFS", $enabledAlgorithms)) {
        return "BFS"; // ✅ BFS 백업 알고리즘
    }

    return "A*"; // ✅ 기본값
}
    
private function handleSwimming(Living $mob): void {
    $position = $mob->getPosition();
    $world = $mob->getWorld();

    $blockAtFeet = $world->getBlockAt((int)$position->x, (int)$position->y, (int)$position->z);
    $blockAtHead = $world->getBlockAt((int)$position->x, (int)$position->y + 1, (int)$position->z);

    // 1. 공기 블록 예외 처리
    if ($blockAtFeet instanceof Air || $blockAtHead instanceof Air) {
        return; // 공기 블록 위에서는 수영 동작 X
    }

    // 2. 잔디 블록 예외 처리 (필요에 따라)
    if ($blockAtFeet instanceof TallGrass || $blockAtHead instanceof TallGrass) {
        return; // 잔디 블록 위에서는 수영 동작 X (원하는 경우)
    }

    // 3. 물 블록에서만 수영 동작
    if ($blockAtFeet->getId() === Block::WATER || $blockAtHead->getId() === Block::WATER) {
        $mob->setMotion(new Vector3(
            $mob->getMotion()->x * 0.9,
            0.3, // 수면 위로 이동하도록 점프
            $mob->getMotion()->z * 0.9
        ));
    }
}
    private function isCollidingWithBlock(Living $mob, Block $block): bool {
    $mobAABB = $mob->getBoundingBox();
    $blockAABB = new AABB(
        $block->getPosition()->x, $block->getPosition()->y, $block->getPosition()->z,
        $block->getPosition()->x + 1, $block->getPosition()->y + 1, $block->getPosition()->z + 1
    );

    return $mobAABB->intersectsWith($blockAABB);
    }

private function findBestPath(Zombie $mob, Vector3 $target): ?array {
    foreach ($this->algorithmPriority as $algorithm) {
        $path = $this->entityAI->findPath($mob->getWorld(), $mob->getPosition(), $target, $algorithm);
        if ($path !== null) {
            return $path;
        }
    }
    return null;
}
    
    private function detectLanding(Living $mob): void {
        $mobId = $mob->getId();
        $isOnGround = $mob->isOnGround();

        if (!isset($this->hasLanded[$mobId]) && $isOnGround) {
            $this->landedTick[$mobId] = Server::getInstance()->getTick();
        }
        $this->hasLanded[$mobId] = $isOnGround;
    }

    private function checkForObstaclesAndJump(Living $mob): void {
    $position = $mob->getPosition();
    $world = $mob->getWorld();
    $yaw = $mob->getLocation()->yaw;
    $angles = [$yaw, $yaw + 30, $yaw - 30]; // 정밀한 장애물 감지

    foreach ($angles as $angle) {
        $direction2D = VectorMath::getDirection2D($angle);
        $directionVector = new Vector3($direction2D->x, 0, $direction2D->y);

        $frontBlockPos = $position->addVector($directionVector);
        $frontBlock = $world->getBlockAt((int)$frontBlockPos->x, (int)$frontBlockPos->y, (int)$frontBlockPos->z);
        $frontBlockAbove = $world->getBlockAt((int)$frontBlockPos->x, (int)$frontBlockPos->y + 1, (int)$frontBlockPos->z);
        
        $heightDiff = $frontBlock->getPosition()->y + 1 - $position->y; // ✅ +1 추가하여 정확한 점프 감지

        // ✅ 평지에서는 점프하지 않음 (높이 차이가 너무 작으면 무시)
        if ($heightDiff < 0.3) {
            continue;
        }

        // ✅ 계단 및 슬랩 감지 → 부드러운 이동 처리
        if ($this->isStairOrSlab($frontBlock) && $frontBlockAbove->isTransparent()) {
            $this->stepUp($mob, $heightDiff);
            return;
        }

        // ✅ 일반 블록 점프 처리
        if ($this->isClimbable($frontBlock) && $frontBlockAbove->isTransparent()) {
            if ($heightDiff <= 1.5) { // ✅ 점프 가능 높이 조정
                $this->jump($mob, $heightDiff);
                return;
            }
        }
    }
}
    
    private function checkFrontBlock(Living $mob): ?Block {
    $position = $mob->getPosition();
    $world = $mob->getWorld();
    $yaw = $mob->getLocation()->yaw;
    $direction2D = VectorMath::getDirection2D($yaw);
    $directionVector = new Vector3($direction2D->x, 0, $direction2D->y);

    $frontBlockX = (int)floor($position->x + $directionVector->x);
    $frontBlockY = (int)$position->y;
    $frontBlockZ = (int)floor($position->z + $directionVector->z);

    return $world->getBlockAt($frontBlockX, $frontBlockY, $frontBlockZ);
}
private function calculateHeightDiff(Living $mob, Block $frontBlock): float {
    return $frontBlock->getPosition()->y + 0.5 - $mob->getPosition()->y;
}

    private function stepUp(Living $mob, float $heightDiff): void {
    if ($heightDiff > 0.5 && $heightDiff <= 1.5) {
        $direction = $mob->getDirectionVector()->normalize()->multiply(0.15); // ✅ 일정한 수평 속도 유지

        // ✅ 더 자연스러운 상승 속도 적용
        $mob->setMotion(new Vector3(
            $direction->x,
            0.2 + ($heightDiff * 0.1), // 점프 높이를 조절하여 부드럽게 상승
            $direction->z
        ));

        // ✅ 착지 후 부드러운 감속 적용
        $this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($mob): void {
            if ($mob->isOnGround()) {
                $mob->setMotion($mob->getMotion()->multiply(0.8)); // 서서히 감속하여 부드러운 착지
            }
        }), 2);
    }
}
    
private function isStairOrSlab(Block $block): bool {
    return $block instanceof Stair || $block instanceof Slab;
}
    private function findNearestPlayer(Zombie $mob): ?Player {
        $closestDistance = PHP_FLOAT_MAX;
        $nearestPlayer = null;

        foreach ($mob->getWorld()->getPlayers() as $player) {
            $distance = $mob->getPosition()->distance($player->getPosition());
            if ($distance < $closestDistance) {
                $closestDistance = $distance;
                $nearestPlayer = $player;
            }
        }

        return $nearestPlayer;
    }
    
    private function attackNearestPlayer(Zombie $mob): void {
    $nearestPlayer = $this->findNearestPlayer($mob);

    if ($nearestPlayer !== null) {
        $distance = $mob->getPosition()->distance($nearestPlayer->getPosition());

        // ✅ 몬스터가 플레이어를 정면으로 바라볼 때만 공격 가능
        $mobDirection = $mob->getDirectionVector();
        $toPlayer = $nearestPlayer->getPosition()->subtractVector($mob->getPosition())->normalize();
        $dotProduct = $mobDirection->dot($toPlayer);

        // ✅ dotProduct가 0.7 이상이면 정면 방향
        if ($distance <= 1.5 && $dotProduct >= 0.7) {
            $damage = $this->plugin->getConfig()->get("attack_damage", 2);
            $event = new EntityDamageByEntityEvent($mob, $nearestPlayer, EntityDamageEvent::CAUSE_ENTITY_ATTACK, $damage);
            $nearestPlayer->attack($event);

            // ✅ 공격 애니메이션 실행
            $mob->broadcastAnimation(new ArmSwingAnimation($mob));
        }
    }
}
    private function moveToPlayer(Zombie $mob, Player $player): void {
    $mobPos = $mob->getPosition();
    $playerPos = $player->getPosition();

    $speed = 0.2; // 속도를 일정하게 설정

    $motion = $playerPos->subtractVector($mobPos)->normalize()->multiply($speed);
    $currentMotion = $mob->getMotion();

    $inertiaFactor = 0.2; // 관성을 줄여서 부드럽게 이동하도록 설정
    $blendedMotion = new Vector3(
        ($currentMotion->x * $inertiaFactor) + ($motion->x * (1 - $inertiaFactor)),
        $currentMotion->y,
        ($currentMotion->z * $inertiaFactor) + ($motion->z * (1 - $inertiaFactor))
    );

    $mob->setMotion($blendedMotion);
    $mob->lookAt($playerPos);

    // 계단 오르기 로직 추가
    $frontBlock = $this->checkFrontBlock($mob);
    if ($frontBlock !== null) {
        $heightDiff = $this->calculateHeightDiff($mob, $frontBlock);
        $this->stepUp($mob, $heightDiff);
    }

    // 낙하 방지 로직 추가
    $this->avoidFalling($mob);
}

private function moveRandomly(Living $mob): void {
    if ($this->changeDirectionTick > Server::getInstance()->getTick()) return;

    $this->changeDirectionTick = Server::getInstance()->getTick() + mt_rand(40, 80); // 2~4초마다 방향 변경

    $directionVectors = [
        new Vector3(1, 0, 0),
        new Vector3(-1, 0, 0),
        new Vector3(0, 0, 1),
        new Vector3(0, 0, -1)
    ];
    $randomDirection = $directionVectors[array_rand($directionVectors)];

    $currentMotion = $mob->getMotion();
    $blendedMotion = new Vector3(
        ($currentMotion->x * 0.8) + ($randomDirection->x * 0.2),
        $currentMotion->y,
        ($currentMotion->z * 0.8) + ($randomDirection->z * 0.2)
    );

    $mob->setMotion($blendedMotion);
}

    
    private function avoidFalling(Living $mob): void {
    $position = $mob->getPosition();
    $world = $mob->getWorld();
    
    $blockBelow = $world->getBlockAt((int)$position->x, (int)$position->y - 1, (int)$position->z);
    
    if ($blockBelow->isTransparent()) {
        $this->changeDirection($mob);
    }
}
private function changeDirection(Living $mob): void {
    $position = $mob->getPosition();
    $world = $mob->getWorld();
    $yaw = $mob->getLocation()->yaw;
    $direction2D = VectorMath::getDirection2D($yaw);
    $frontVector = new Vector3($direction2D->x, 0, $direction2D->y);

    $frontBlock = $world->getBlockAt((int)floor($position->x + $frontVector->x), (int)$position->y, (int)floor($position->z + $frontVector->z));

    // ✅ 계단 위에서는 방향을 바꾸지 않음
    if ($this->isStairOrSlab($frontBlock)) {
        return;
    }
}
    public function jump(Living $mob, float $heightDiff = 1.0): void {
    // 낙하 속도 리셋 (너무 빠르게 낙하하지 않도록)
    if ($mob->getMotion()->y < -0.08) {
        $mob->setMotion(new Vector3(
            $mob->getMotion()->x,
            -0.08,
            $mob->getMotion()->z
        ));
    }

    // 기본 점프 힘 설정
    $baseJumpForce = 0.42; // 기본 점프력
    $extraJumpBoost = min(0.1 * $heightDiff, 0.3); // 높이에 따라 추가 점프력 조정

    $jumpForce = $baseJumpForce + $extraJumpBoost;
    
    if ($mob->isOnGround() || $mob->getMotion()->y <= 0.1) {
        $direction = $mob->getDirectionVector();
        $horizontalSpeed = 0.1; // 수평 이동 속도 추가

        $mob->setMotion(new Vector3(
            $mob->getMotion()->x * 0.5 + ($direction->x * $horizontalSpeed),
            $jumpForce,
            $mob->getMotion()->z * 0.5 + ($direction->z * $horizontalSpeed)
        ));
    }
}
    
    private function isClimbable(Block $block): bool {
    $climbableBlocks = [
        "pocketmine:block:snow_layer",
        "pocketmine:block:fence",
        "pocketmine:block:glass",
        "pocketmine:block:frame"
    ];
    return $block->isSolid() || in_array($block->getName(), $climbableBlocks);
}
}
