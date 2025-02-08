<?php

namespace HybridMobAI;

use pocketmine\entity\Living;
use pocketmine\entity\Location;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\world\World;
use pocketmine\entity\EntityDataHelper;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pockdtmine\player\Player;

class Zombie extends Living {
    private Main $plugin;

    public function __construct(Location $location, CompoundTag $nbt, Main $plugin) {
        parent::__construct($location, $nbt);
        $this->plugin = $plugin;
        $this->adjustSpawnLocation();
    }

    protected function initEntity(CompoundTag $nbt): void {
        parent::initEntity($nbt);
    }

    /** ✅ 블록 충돌 방지: 스폰 위치 조정 */
    private function adjustSpawnLocation(): void {
        $world = $this->getWorld();
        $pos = $this->getPosition();
        $block = $world->getBlockAt((int) $pos->x, (int) $pos->y, (int) $pos->z);

        if ($block !== null && !$block->isTransparent()) {
            $this->teleport($pos->add(0, 1, 0));
        }
    }

    public function onUpdate(int $currentTick): bool {
    if (!$this->isAlive()) {
        return false;
    }

    //$this->getLogger()->info("좀비 AI 실행 중: " . $this->getId());

    return parent::onUpdate($currentTick);
}

    public function getName(): string {
        return "Zombie";
    }

    protected function getInitialSizeInfo(): EntitySizeInfo {
        return new EntitySizeInfo(1.95, 0.6);
    }

    /** ✅ 반환 타입을 `string`으로 변경 */
    public static function getNetworkTypeId(): string {
        return "minecraft:zombie";
    }
    public function hasClearLineOfSight(Player $player): bool {
    $mobPos = $this->getPosition();
    $playerPos = $player->getPosition();

    $world = $this->getWorld();
    $steps = 10; // 시야 체크할 단계 수
    $stepX = ($playerPos->x - $mobPos->x) / $steps;
    $stepY = ($playerPos->y - $mobPos->y) / $steps;
    $stepZ = ($playerPos->z - $mobPos->z) / $steps;

    for ($i = 0; $i < $steps; $i++) {
        $checkX = (int) floor($mobPos->x + ($stepX * $i));
        $checkY = (int) floor($mobPos->y + ($stepY * $i));
        $checkZ = (int) floor($mobPos->z + ($stepZ * $i));

        $block = $world->getBlockAt($checkX, $checkY, $checkZ);

        if (!$block->isTransparent()) {
            return false; // 중간에 블록이 있으면 시야 차단
        }
    }

    return true; // 모든 경로가 투명하면 시야 확보됨
}
}
