<?php

namespace HybridMobAI;

use pocketmine\scheduler\Task;
use pocketmine\scheduler\ClosureTask;
use pocketmine\Server;
use pocketmine\entity\Living;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\math\VectorMath;
use pocketmine\block\Block;
use pocketmine\math\AxisAlignedBB as AABB;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\entity\animation\ArmSwingAnimation;
use pocketmine\block\Stair;
use pocketmine\block\Slab;

class MobAITask extends Task {
    private Main $plugin;
    private int $tickCounter = 0;
    private array $hasLanded = [];
    private array $landedTick = [];
    private int $changeDirectionTick = 0;
    private bool $aiEnabled;
    private EntityAI $entityAI;
    private array $algorithmPriority;

    public function __construct(Main $plugin, bool $aiEnabled, array $algorithmPriority) {
    $this->plugin = $plugin;
    $this->aiEnabled = $aiEnabled;
    $this->algorithmPriority = $algorithmPriority;
    $this->entityAI = new EntityAI();
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

    private function handleMobAI(Zombie $mob): void {
    if (!$this->aiEnabled) {
        $nearestPlayer = $this->findNearestPlayer($mob);
        if ($nearestPlayer !== null) {
            $this->moveToPlayer($mob, $nearestPlayer);
        } else {
            $this->moveRandomly($mob);
        }
    } else {
        if (($player = $this->findNearestPlayer($mob)) !== null) {
            if ($this->entityAI->hasPath($mob)) {
                $this->entityAI->moveAlongPath($mob);
            } else {
                // ✅ 인자 순서 수정 (올바른 순서: world, start, goal, algorithm, callback)
                $this->entityAI->findPathAsync(
                    $mob->getWorld(),
                    $mob->getPosition(),
                    $player->getPosition(),
                    "A*", // ✅ 알고리즘을 올바르게 전달
                    function (?array $path) use ($mob) {
                        if ($path !== null) {
                            $this->entityAI->setPath($mob, $path);
                        } else {
                            $this->moveRandomly($mob);
                        }
                    }
                );
            }
        } else {
            $this->moveRandomly($mob);
        }
    }

    $this->detectLanding($mob);
    $this->checkForObstaclesAndJump($mob);
    $this->attackNearestPlayer($mob);
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
    $angles = [$yaw, $yaw + 45, $yaw - 45];
    
    foreach ($angles as $angle) {
    $direction2D = VectorMath::getDirection2D($angle);
    $directionVector = new Vector3($direction2D->x, 0, $direction2D->y);

    $frontBlockX = (int)floor($position->x + $directionVector->x);
    $frontBlockY = (int)$position->y;
    $frontBlockZ = (int)floor($position->z + $directionVector->z);

    $frontBlock = $world->getBlockAt($frontBlockX, $frontBlockY, $frontBlockZ);
    $frontBlockAbove = $world->getBlockAt($frontBlockX, $frontBlockY + 1, $frontBlockZ);

    $heightDiff = $frontBlock->getPosition()->y + 0.5 - $position->y;

    if ($heightDiff < 0) {
            continue;
    }

        // ✅ 계단 감지 (연속된 계단에서도 점프 가능하게 수정)
    if ($this->isStairOrSlab($frontBlock)) {
        $this->plugin()->getLogger()->info("계단감지");
        if ($frontBlockAbove->isTransparent()) {
            $this->stepUP($mob, $heightDiff);
            $this->plugin()->getLogger()->info("계단");
            return;
        }
    }
    // ✅ 점프 조건 강화 (블록이 앞에 있고, 점프 가능한 경우)
    if ($this->isClimbable($frontBlock) && $frontBlockAbove->isTransparent()) {
        if ($heightDiff <= 1.5 && $heightDiff > 0) {
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
    
    // 정확한 방향 계산을 위해 0.6m 앞쪽을 확인
    $direction = VectorMath::getDirection2D($yaw)->mult(0.6);
    
    $frontX = (int)floor($position->x + $direction->x);
    $frontY = (int)floor($position->y + 0.5); // 몸 중앙 높이
    $frontZ = (int)floor($position->z + $direction->y);
    
    return $world->getBlockAt($frontX, $frontY, $frontZ);
}
private function calculateHeightDiff(Living $mob, Block $frontBlock): float {
    return $frontBlock->getPosition()->y + 0.5 - $mob->getPosition()->y;
}
    
    private function stepUp(Living $mob, float $heightDiff): void {
    $direction = $mob->getDirectionVector()->normalize();
    $horizontalBoost = 0.25; // 수평 이동 추가 힘
    
    $mob->setMotion(new Vector3(
        $direction->x * $horizontalBoost,
        0.42 + min($heightDiff * 0.2, 0.3), // 높이에 따른 점프력 조정
        $direction->z * $horizontalBoost
    ));
    
    // 0.1초 후 추가 점프 체크
    $this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($mob) {
        $this->checkForObstaclesAndJump($mob);
    }), 2);
}

    private function isCollidingWithStair(Living $mob, Block $block): bool {
    if($block instanceof Stair) {
        $mobPos = $mob->getPosition();
        $stairFacing = $block->getFacing();
        $mobDirection = $mob->getHorizontalFacing();
        
        // 계단 방향과 몹 진행방향이 일치할 때만 충돌 처리
        return $stairFacing === $mobDirection;
    }
    return false;
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
    
    // 계단 위에서는 방향 변경하지 않음
    if($blockBelow->isTransparent() && !$this->isStairOrSlab($blockBelow)) {
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
