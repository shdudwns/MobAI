<?php

namespace HybridMobAI;

use pocketmine\scheduler\AsyncTask;
use pocketmine\math\Vector3;
use pocketmine\Server;
use pocketmine\block\BlockTypeIds;

class SpawnZombiesTask extends AsyncTask {
    private $worldId;
    private $playerX;
    private $playerY;
    private $playerZ;

    public function __construct(int $worldId, float $playerX, float $playerY, float $playerZ) {
        $this->worldId = $worldId;
        $this->playerX = $playerX;
        $this->playerY = $playerY;
        $this->playerZ = $playerZ;
    }

    public function onRun(): void {
        $positions = [];
        for ($i = 0; $i < 5; $i++) { // 5개의 좀비 생성
            $x = mt_rand((int)($this->playerX - 20), (int)($this->playerX + 20));
            $z = mt_rand((int)($this->playerZ - 20), (int)($this->playerZ + 20));
            $y = $this->findSafeY($x, $z);

            if ($y !== null) {
                $positions[] = new Vector3($x, $y, $z);
            }
        }
        $this->setResult($positions);
    }

    private function findSafeY(int $x, int $z): ?int {
        if (!$this->isChunkLoaded($x >> 4, $z >> 4)) {
            return null;
        }
        for ($y = 255; $y > 0; $y--) {
            $blockId = $this->getBlockIdAt($x, $y, $z); // 가상의 함수, 실제 구현에 맞춰 수정 필요
            if ($blockId !== BlockTypeIds::AIR && $blockId !== BlockTypeIds::WATER) {
                return $y + 1;
            }
        }
        return null;
    }

    private function isChunkLoaded(int $chunkX, int $chunkZ): bool {
        // 청크가 로드되었는지 확인하는 로직
        // 이 부분은 PocketMine-MP의 API를 사용하여 구현해야 합니다.
        return true; // 기본값으로 true를 반환 (실제 구현 필요)
    }

    private function getBlockIdAt(int $x, int $y, int $z): int {
        // 실제 블록 ID를 반환하는 로직을 구현
        // 이 부분은 PocketMine-MP의 API를 사용하여 구현해야 합니다.
        return BlockTypeIds::AIR; // 기본값으로 AIR를 반환 (실제 구현 필요)
    }

    public function onCompletion(): void {
        $server = Server::getInstance();
        $world = $server->getWorldManager()->getWorld($this->worldId);
        if ($world !== null) {
            foreach ($this->getResult() as $position) {
                $server->getPluginManager()->getPlugin("HybridMobAI")->spawnZombieAt($world, $position);
            }
        }
    }
}
