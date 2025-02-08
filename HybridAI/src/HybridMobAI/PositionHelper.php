<?php
namespace HybridMobAI;

use pocketmine\math\Vector3;
use pocketmine\world\Position;

class PositionHelper {
    public static function toVector3(mixed $position): Vector3 {
        if ($position instanceof Vector3) {
            return $position;
        }
        if ($position instanceof Position) {
            return new Vector3((float)$position->x, (float)$position->y, (float)$position->z);
        }
        throw new \InvalidArgumentException("Invalid position type. Expected Vector3 or Position.");
    }
}
