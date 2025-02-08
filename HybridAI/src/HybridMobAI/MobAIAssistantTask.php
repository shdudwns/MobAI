<?php
namespace HybridMobAI;

use pocketmine\scheduler\AsyncTask;
use pocketmine\Server;
use pocketmine\math\Vector3;
use pocketmine\player\Player;
use pocketmine\world\World;

class MobAIAssistantTask extends AsyncTask {
    private int $mobId;
    private string $worldName;

    public function __construct(int $mobId, string $worldName) {
        $this->mobId = $mobId;
        $this->worldName = $worldName;
    }

    public function onRun(): void {
        $server = Server::getInstance();
        $world = $server->getWorldManager()->getWorldByName($this->worldName);
        if (!$world instanceof World) return;

        $mob = $server->findEntity($this->mobId);
        if ($mob === null) return;

        $ai = new EntityAI($server->getPluginManager()->getPlugin("HybridAI"));
        $player = $ai->findNearestPlayer($mob);

        if ($player !== null) {
            $ai->findPathAsync($world, $mob->getPosition(), $player->getPosition(), "A*", function ($path) use ($mob, $ai) {
                if ($path !== null) {
                    $ai->setPath($mob, $path);
                }
            });
        }
    }
}
