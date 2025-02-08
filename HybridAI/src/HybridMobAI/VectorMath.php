<?php

namespace pocketmine\math;

class VectorMath {

    /**
     * 2D 벡터의 방향을 라디안 각도로 반환합니다.
     *
     * @param Vector3 $v
     * @return float
     */
    public static function getDirection2D(Vector3 $v): float {
        return atan2($v->z, $v->x);
    }

    /**
     * 벡터를 주어진 각도만큼 회전시킵니다 (Yaw, Pitch, Roll).
     *  2D 회전만 사용하도록 수정.
     * @param Vector3 $vector
     * @param float $angle
     * @return Vector3
     */
    public static function rotateVector(Vector3 $vector, float $angle): Vector3 {
        $yaw = deg2rad($angle);
        $cosYaw = cos($yaw);
        $sinYaw = sin($yaw);

        $x = $vector->x;
        $z = $vector->z;

        $newX = $x * $cosYaw - $z * $sinYaw;
        $newZ = $x * $sinYaw + $z * $cosYaw;

        return new Vector3($newX, $vector->y, $newZ);
    }

    /**
     * 벡터를 더합니다.
     *
     * @param Vector3 $v1
     * @param Vector3 $v2
     * @return Vector3
     */
    public static function add(Vector3 $v1, Vector3 $v2): Vector3 {
        return new Vector3($v1->x + $v2->x, $v1->y + $v2->y, $v1->z + $v2->z);
    }



    /**
     * 벡터의 각 성분을 정수로 변환합니다.
     *
     * @param Vector3 $v
     * @return Vector3
     */
    public static function toInt(Vector3 $v): Vector3 {
        return new Vector3((int)$v->x, (int)$v->y, (int)$v->z);
    }
}
