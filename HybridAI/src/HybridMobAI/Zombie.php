<?php

namespace HybridMobAI;

use pocketmine\entity\Monster;
use pocketmine\entity\Entity;
use pocketmine\nbt\tag\CompoundTag;

class Zombie extends Monster {
    public const NETWORK_ID = Entity::ZOMBIE;

    public function getName(): string {
        return "Zombie";
    }
}