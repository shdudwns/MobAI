<?php

namespace HybridMobAI;

use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\entity\Living;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use pocketmine\player\Player;
use pocketmine\math\VectorMath;
use pocketmine\block\Block;
use pocketmine\scheduler\ClosureTask;

class MobAITask extends Task {
    private Main $plugin;
    private int $tickCounter = 0;
    private array $isJumping = [];

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function onRun(): void {
        $this->tickCounter++;

        if ($this->tickCounter % 2 !== 0) {
            return;
        }

        foreach (Server::getInstance()->getWorldManager()->getWorlds() as $world) {
            foreach ($world->getEntities() as $entity) {
                if ($entity instanceof Zombie) {
                    $this->handleMobAI($entity);
                }
            }
        }
    }

    private function handleMobAI(Zombie $mob): void {
        $nearestPlayer = $this->findNearestPlayer($mob);
        if ($nearestPlayer !== null) {
            $this->moveToPlayer($mob, $nearestPlayer);
        } else {
            $this->moveRandomly($mob);
        }

        $this->checkForObstaclesAndJump($mob);
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

    private function moveToPlayer(Zombie $mob, Player $player): void {
        $mobPos = $mob->getPosition();
        $playerPos = $player->getPosition();

        $mobVec3 = new Vector3($mobPos->getX(), $mobPos->getY(), $mobPos->getZ());
        $playerVec3 = new Vector3($playerPos->getX(), $playerPos->getY(), $playerPos->getZ());

        $distance = $mobVec3->distance($playerVec3);
        $speed = 0.2;
        if ($distance < 5) {
            $speed *= $distance / 5;
        }

        $motion = $playerVec3->subtractVector($mobVec3)->normalize()->multiply($speed);

        $currentMotion = $mob->getMotion();
        $blendedMotion = new Vector3(
            ($currentMotion->getX() * 0.5) + ($motion->getX() * 0.5),
            $currentMotion->getY(),
            ($currentMotion->getZ() * 0.5) + ($motion->getZ() * 0.5)
        );

        $mob->setMotion($blendedMotion);
        $mob->lookAt($playerPos);
    }

    public function moveRandomly(Living $mob): void {
        $directionVectors = [
            new Vector3(1, 0, 0),
            new Vector3(-1, 0, 0),
            new Vector3(0, 0, 1),
            new Vector3(0, 0, -1)
        ];
        $randomDirection = $directionVectors[array_rand($directionVectors)];

        $currentMotion = $mob->getMotion();
        $blendedMotion = new Vector3(
            ($currentMotion->getX() * 0.8) + ($randomDirection->getX() * 0.2),
            $currentMotion->getY(),
            ($currentMotion->getZ() * 0.8) + ($randomDirection->getZ() * 0.2)
        );

        $mob->setMotion($blendedMotion);
    }

    private function isClimbable(Block $block): bool {
        $climbableBlocks = [
            "pocketmine:block:slab",
            "pocketmine:block:stairs",
            "pocketmine:block:snow_layer"
        ];
        return $block->isSolid() || in_array($block->getName(), $climbableBlocks);
    }

    private function checkForObstaclesAndJump(Living $mob): void {
        $entityId = $mob->getId();

        if (isset($this->isJumping[$entityId]) && $this->isJumping[$entityId]) {
            if ($mob->isOnGround()) {
                $this->isJumping[$entityId] = false;
            }
            return;
        }

        $position = $mob->getPosition();
        $world = $mob->getWorld();

        $yaw = $mob->getLocation()->getYaw();
        $direction2D = VectorMath::getDirection2D($yaw);
        $directionVector = new Vector3($direction2D->getX(), 0, $direction2D->getY());

        // 좌우 블록 감지 로직 개선
        $leftVector = new Vector3(-$directionVector->getZ(), 0, $directionVector->getX());
        $rightVector = new Vector3($directionVector->getZ(), 0, -$directionVector->getX());

        $leftBlockX = (int)floor($position->getX() + $leftVector->getX());
        $leftBlockY = (int)floor($position->getY());
        $leftBlockZ = (int)floor($position->getZ() + $leftVector->getZ());
        $leftBlock = $world->getBlockAt($leftBlockX, $leftBlockY, $leftBlockZ);

        $rightBlockX = (int)floor($position->getX() + $rightVector->getX());
        $rightBlockY = (int)floor($position->getY());
        $rightBlockZ = (int)floor($position->getZ() + $rightVector->getZ());
        $rightBlock = $world->getBlockAt($rightBlockX, $rightBlockY, $rightBlockZ);

        if ($leftBlock->isSolid() && $rightBlock->isSolid()) {
            return;
        }

        // 앞 블록 감지 로직 개선 (높이차 고려, 1칸 + 대각선 방향 1칸 확인)
        for ($i = 0; $i <= 1; $i++) { // 0칸, 1칸 확인
            for ($j = -1; $j <= 1; $j++) { // -1 (왼쪽 대각선), 0 (정면), 1 (오른쪽 대각선) 확인
                $frontBlockX = (int)floor($position->getX() + $directionVector->getX() * $i + $leftVector->getX() * $j);
                $frontBlockY = (int)floor($position->getY());
                $frontBlockZ = (int)floor($position->getZ() + $directionVector->getZ() * $i + $leftVector->getZ() * $j);

                $frontBlock = $world->getBlockAt($frontBlockX, $frontBlockY, $frontBlockZ);
                $frontBlockAbove = $world->getBlockAt($frontBlockX, $frontBlockY + 1, $frontBlockZ);

                $currentHeight = (int)floor($position->getY());
                $blockHeight = (int)floor($frontBlock->getPosition()->getY());
                $heightDiff = $blockHeight - $currentHeight;

                // 내려가는 상황 감지 및 점프 방지
                if ($heightDiff < 0) {
                    continue; // 다음 블록 확인
                }

                if ($this->isClimbable($frontBlock) && $frontBlockAbove->isTransparent()) {
                    // 좀 더 가까이 다가갔을 때 점프하도록 delay 추가 (값 조절 가능)
                    $this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function (int $currentTick) use ($mob, $heightDiff) {
                        $this->jump($mob, $heightDiff);
                        $this->isJumping[$mob->getId()] = true;
                    }), 2); // 2틱 delay

                    return;
                }
            }
        }
    }

    <?php

namespace HybridMobAI;

use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\entity\Living;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use pocketmine\player\Player;
use pocketmine\math\VectorMath;
use pocketmine\block\Block;
use pocketmine\entity\Zombie;
use pocketmine\scheduler\ClosureTask;

class MobAITask extends Task {
    private Main $plugin;
    private int $tickCounter = 0;
    private array $isJumping = [];

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function onRun(): void {
        $this->tickCounter++;

        if ($this->tickCounter % 2 !== 0) {
            return;
        }

        foreach (Server::getInstance()->getWorldManager()->getWorlds() as $world) {
            foreach ($world->getEntities() as $entity) {
                if ($entity instanceof Zombie) {
                    $this->handleMobAI($entity);
                }
            }
        }
    }

    private function handleMobAI(Zombie $mob): void {
        $nearestPlayer = $this->findNearestPlayer($mob);
        if ($nearestPlayer !== null) {
            $this->moveToPlayer($mob, $nearestPlayer);
        } else {
            $this->moveRandomly($mob);
        }

        $this->checkForObstaclesAndJump($mob);
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

    private function moveToPlayer(Zombie $mob, Player $player): void {
        $mobPos = $mob->getPosition();
        $playerPos = $player->getPosition();

        $mobVec3 = new Vector3($mobPos->getX(), $mobPos->getY(), $mobPos->getZ());
        $playerVec3 = new Vector3($playerPos->getX(), $playerPos->getY(), $playerPos->getZ());

        $distance = $mobVec3->distance($playerVec3);
        $speed = 0.2;
        if ($distance < 5) {
            $speed *= $distance / 5;
        }

        $motion = $playerVec3->subtractVector($mobVec3)->normalize()->multiply($speed);

        $currentMotion = $mob->getMotion();
        $blendedMotion = new Vector3(
            ($currentMotion->getX() * 0.5) + ($motion->getX() * 0.5),
            $currentMotion->getY(),
            ($currentMotion->getZ() * 0.5) + ($motion->getZ() * 0.5)
        );

        $mob->setMotion($blendedMotion);
        $mob->lookAt($playerPos);
    }

    public function moveRandomly(Living $mob): void {
        $directionVectors = [
            new Vector3(1, 0, 0),
            new Vector3(-1, 0, 0),
            new Vector3(0, 0, 1),
            new Vector3(0, 0, -1)
        ];
        $randomDirection = $directionVectors[array_rand($directionVectors)];

        $currentMotion = $mob->getMotion();
        $blendedMotion = new Vector3(
            ($currentMotion->getX() * 0.8) + ($randomDirection->getX() * 0.2),
            $currentMotion->getY(),
            ($currentMotion->getZ() * 0.8) + ($randomDirection->getZ() * 0.2)
        );

        $mob->setMotion($blendedMotion);
    }

    private function isClimbable(Block $block): bool {
        $climbableBlocks = [
            "pocketmine:block:slab",
            "pocketmine:block:stairs",
            "pocketmine:block:snow_layer"
        ];
        return $block->isSolid() || in_array($block->getName(), $climbableBlocks);
    }

    private function checkForObstaclesAndJump(Living $mob): void {
        $entityId = $mob->getId();

        if (isset($this->isJumping[$entityId]) && $this->isJumping[$entityId]) {
            if ($mob->isOnGround()) {
                $this->isJumping[$entityId] = false;
            }
            return;
        }

        $position = $mob->getPosition();
        $world = $mob->getWorld();

        $yaw = $mob->getLocation()->getYaw();
        $direction2D = VectorMath::getDirection2D($yaw);
        $directionVector = new Vector3($direction2D->getX(), 0, $direction2D->getY());

        // 좌우 블록 감지 로직 개선
        $leftVector = new Vector3(-$directionVector->getZ(), 0, $directionVector->getX());
        $rightVector = new Vector3($directionVector->getZ(), 0, -$directionVector->getX());

        $leftBlockX = (int)floor($position->getX() + $leftVector->getX());
        $leftBlockY = (int)floor($position->getY());
        $leftBlockZ = (int)floor($position->getZ() + $leftVector->getZ());
        $leftBlock = $world->getBlockAt($leftBlockX, $leftBlockY, $leftBlockZ);

        $rightBlockX = (int)floor($position->getX() + $rightVector->getX());
        $rightBlockY = (int)floor($position->getY());
        $rightBlockZ = (int)floor($position->getZ() + $rightVector->getZ());
        $rightBlock = $world->getBlockAt($rightBlockX, $rightBlockY, $rightBlockZ);

        if ($leftBlock->isSolid() && $rightBlock->isSolid()) {
            return;
        }

        // 앞 블록 감지 로직 개선 (높이차 고려, 1칸 + 대각선 방향 1칸 확인)
        for ($i = 0; $i <= 1; $i++) { // 0칸, 1칸 확인
            for ($j = -1; $j <= 1; $j++) { // -1 (왼쪽 대각선), 0 (정면), 1 (오른쪽 대각선) 확인
                $frontBlockX = (int)floor($position->getX() + $directionVector->getX() * $i + $leftVector->getX() * $j);
                $frontBlockY = (int)floor($position->getY());
                $frontBlockZ = (int)floor($position->getZ() + $directionVector->getZ() * $i + $leftVector->getZ() * $j);

                $frontBlock = $world->getBlockAt($frontBlockX, $frontBlockY, $frontBlockZ);
                $frontBlockAbove = $world->getBlockAt($frontBlockX, $frontBlockY + 1, $frontBlockZ);

                $currentHeight = (int)floor($position->getY());
                $blockHeight = (int)floor($frontBlock->getPosition()->getY());
                $heightDiff = $blockHeight - $currentHeight;

                // 내려가는 상황 감지 및 점프 방지
                if ($heightDiff < 0) {
                    continue; // 다음 블록 확인
                }

                if ($this->isClimbable($frontBlock) && $frontBlockAbove->isTransparent()) {
                    // 좀 더 가까이 다가갔을 때 점프하도록 delay 추가 (값 조절 가능)
                    $this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function (int $currentTick) use ($mob, $heightDiff) {
                        $this->jump($mob, $heightDiff);
                        $this->isJumping[$mob->getId()] = true;
                    }), 2); // 2틱 delay

                    return;
                }
            }
        }
    }

    <?php

namespace HybridMobAI;

use pocketmine\scheduler\Task;
use pocketmine\Server;
use pocketmine\entity\Living;
use pocketmine\math\Vector3;
use pocketmine\world\World;
use pocketmine\player\Player;
use pocketmine\math\VectorMath;
use pocketmine\block\Block;
use pocketmine\entity\Zombie;
use pocketmine\scheduler\ClosureTask;

class MobAITask extends Task {
    private Main $plugin;
    private int $tickCounter = 0;
    private array $isJumping = [];

    public function __construct(Main $plugin) {
        $this->plugin = $plugin;
    }

    public function onRun(): void {
        $this->tickCounter++;

        if ($this->tickCounter % 2 !== 0) {
            return;
        }

        foreach (Server::getInstance()->getWorldManager()->getWorlds() as $world) {
            foreach ($world->getEntities() as $entity) {
                if ($entity instanceof Zombie) {
                    $this->handleMobAI($entity);
                }
            }
        }
    }

    private function handleMobAI(Zombie $mob): void {
        $nearestPlayer = $this->findNearestPlayer($mob);
        if ($nearestPlayer !== null) {
            $this->moveToPlayer($mob, $nearestPlayer);
        } else {
            $this->moveRandomly($mob);
        }

        $this->checkForObstaclesAndJump($mob);
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

    private function moveToPlayer(Zombie $mob, Player $player): void {
        $mobPos = $mob->getPosition();
        $playerPos = $player->getPosition();

        $mobVec3 = new Vector3($mobPos->getX(), $mobPos->getY(), $mobPos->getZ());
        $playerVec3 = new Vector3($playerPos->getX(), $playerPos->getY(), $playerPos->getZ());

        $distance = $mobVec3->distance($playerVec3);
        $speed = 0.2;
        if ($distance < 5) {
            $speed *= $distance / 5;
        }

        $motion = $playerVec3->subtractVector($mobVec3)->normalize()->multiply($speed);

        $currentMotion = $mob->getMotion();
        $blendedMotion = new Vector3(
            ($currentMotion->getX() * 0.5) + ($motion->getX() * 0.5),
            $currentMotion->getY(),
            ($currentMotion->getZ() * 0.5) + ($motion->getZ() * 0.5)
        );

        $mob->setMotion($blendedMotion);
        $mob->lookAt($playerPos);
    }

    public function moveRandomly(Living $mob): void {
        $directionVectors = [
            new Vector3(1, 0, 0),
            new Vector3(-1, 0, 0),
            new Vector3(0, 0, 1),
            new Vector3(0, 0, -1)
        ];
        $randomDirection = $directionVectors[array_rand($directionVectors)];

        $currentMotion = $mob->getMotion();
        $blendedMotion = new Vector3(
            ($currentMotion->getX() * 0.8) + ($randomDirection->getX() * 0.2),
            $currentMotion->getY(),
            ($currentMotion->getZ() * 0.8) + ($randomDirection->getZ() * 0.2)
        );

        $mob->setMotion($blendedMotion);
    }

    private function isClimbable(Block $block): bool {
        $climbableBlocks = [
            "pocketmine:block:slab",
            "pocketmine:block:stairs",
            "pocketmine:block:snow_layer"
        ];
        return $block->isSolid() || in_array($block->getName(), $climbableBlocks);
    }

    private function checkForObstaclesAndJump(Living $mob): void {
        $entityId = $mob->getId();

        if (isset($this->isJumping[$entityId]) && $this->isJumping[$entityId]) {
            if ($mob->isOnGround()) {
                $this->isJumping[$entityId] = false;
            }
            return;
        }

        $position = $mob->getPosition();
        $world = $mob->getWorld();

        $yaw = $mob->getLocation()->getYaw();
        $direction2D = VectorMath::getDirection2D($yaw);
        $directionVector = new Vector3($direction2D->getX(), 0, $direction2D->getY());

        // 좌우 블록 감지 로직 개선
        $leftVector = new Vector3(-$directionVector->getZ(), 0, $directionVector->getX());
        $rightVector = new Vector3($directionVector->getZ(), 0, -$directionVector->getX());

        $leftBlockX = (int)floor($position->getX() + $leftVector->getX());
        $leftBlockY = (int)floor($position->getY());
        $leftBlockZ = (int)floor($position->getZ() + $leftVector->getZ());
        $leftBlock = $world->getBlockAt($leftBlockX, $leftBlockY, $leftBlockZ);

        $rightBlockX = (int)floor($position->getX() + $rightVector->getX());
        $rightBlockY = (int)floor($position->getY());
        $rightBlockZ = (int)floor($position->getZ() + $rightVector->getZ());
        $rightBlock = $world->getBlockAt($rightBlockX, $rightBlockY, $rightBlockZ);

        if ($leftBlock->isSolid() && $rightBlock->isSolid()) {
            return;
        }

        // 앞 블록 감지 로직 개선 (높이차 고려, 1칸 + 대각선 방향 1칸 확인)
        for ($i = 0; $i <= 1; $i++) { // 0칸, 1칸 확인
            for ($j = -1; $j <= 1; $j++) { // -1 (왼쪽 대각선), 0 (정면), 1 (오른쪽 대각선) 확인
                $frontBlockX = (int)floor($position->getX() + $directionVector->getX() * $i + $leftVector->getX() * $j);
                $frontBlockY = (int)floor($position->getY());
                $frontBlockZ = (int)floor($position->getZ() + $directionVector->getZ() * $i + $leftVector->getZ() * $j);

                $frontBlock = $world->getBlockAt($frontBlockX, $frontBlockY, $frontBlockZ);
                $frontBlockAbove = $world->getBlockAt($frontBlockX, $frontBlockY + 1, $frontBlockZ);

                $currentHeight = (int)floor($position->getY());
                $blockHeight = (int)floor($frontBlock->getPosition()->getY());
                $heightDiff = $blockHeight - $currentHeight;

                // 내려가는 상황 감지 및 점프 방지
                if ($heightDiff < 0) {
                    continue; // 다음 블록 확인
                }

                if ($this->isClimbable($frontBlock) && $frontBlockAbove->isTransparent()) {
                    // 좀 더 가까이 다가갔을 때 점프하도록 delay 추가 (값 조절 가능)
                    $this->plugin->getScheduler()->scheduleDelayedTask(new ClosureTask(function (int $currentTick) use ($mob, $heightDiff) {
                        $this->jump($mob, $heightDiff);
                        $this->isJumping[$mob->getId()] = true;
                    }), 2); // 2틱 delay

                    return;
                }
            }
        }
    }

    public function jump(Living $mob, float $heightDiff = 1.0): void {
    $jumpForce = min(0.6 + ($heightDiff * 0.2), 1.0);

    if (!$mob->isOnGround()) {
        return;
    }

    $mob->setMotion(new Vector3(
        $mob->getMotion()->getX(),
        $jumpForce,
        $mob->getMotion()->getZ()
    ));
}
}
