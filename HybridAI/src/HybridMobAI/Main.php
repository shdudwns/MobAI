<?php

namespace HybridMobAI;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\entity\EntityDamageEvent;
use pocketmine\event\entity\EntityDamageByEntityEvent;
use pocketmine\event\entity\EntitySpawnEvent;
use pocketmine\entity\Living;
use pocketmine\entity\EntityFactory;
use pocketmine\entity\EntityDataHelper;
use pocketmine\world\World;
use pocketmine\math\Vector3;
use pocketmine\entity\Location;
use pocketmine\scheduler\ClosureTask;
use pocketmine\nbt\tag\CompoundTag;
use pocketmine\player\Player;
use pocketmine\entity\Zombie as PmmpZombie;

class Main extends PluginBase implements Listener {

    private ?MobAITask $mobAITask = null;
    
    public function onEnable(): void {
    $this->saveDefaultConfig(); // config.yml 자동 생성
    $this->initializeConfig();
    $this->reloadAISettings();
    $this->getLogger()->info("HybridMobAI 플러그인 활성화");
    EntityFactory::getInstance()->register(Zombie::class, function(World $world, CompoundTag $nbt): Zombie {
        return new Zombie(EntityDataHelper::parseLocation($nbt, $world), $nbt, $this);
    }, ['Zombie', 'minecraft:zombie']);

    $this->getServer()->getPluginManager()->registerEvents($this, $this);

    // ✅ MobAITask 실행 로그 추가
    $this->getLogger()->info("MobAITask 실행 중...");    
    $spawnInterval = 600; // 600 ticks (30초)
    //$this->getScheduler()->scheduleRepeatingTask(new ClosureTask(fn() => $this->spawnRandomZombies()), $spawnInterval);
    }
    private function initializeConfig(): void {
    $defaultConfig = [
        "AI" => [
            "enabled" => true,
            "pathfinding_priority" => ["A*", "BFS", "DFS", "Dijkstra", "Greedy"],
            "movement" => [
                "random" => true,
                "chase_player" => true,
                "avoid_obstacles" => true
            ]
        ]
    ];

    foreach ($defaultConfig as $key => $value) {
        if (!$this->getConfig()->exists($key)) {
            $this->getConfig()->set($key, $value);
        }
    }
    $this->saveConfig();
}

public function reloadAISettings(): void {
    $config = $this->getConfig()->get("AI");
    $aiEnabled = $config["enabled"];
    $algorithmPriority = $config["pathfinding_priority"];
    $this->getScheduler()->scheduleRepeatingTask(new MobAITask($this, $aiEnabled, $algorithmPriority), 20);
}
    
    public function getMobAITask(): ?MobAITask {
        return $this->mobAITask;
    }
    /** ✅ 기본 좀비 스폰 시 커스텀 좀비로 교체 */
    public function onEntitySpawn(EntitySpawnEvent $event): void {
        $entity = $event->getEntity();
        if ($entity instanceof PmmpZombie) {
            $this->getScheduler()->scheduleDelayedTask(new ClosureTask(fn() => $this->replaceWithCustomZombie($entity)), 2);
        }
    }

    private function replaceWithCustomZombie(PmmpZombie $pmmpZombie): void {
        $world = $pmmpZombie->getWorld();
        $location = $pmmpZombie->getLocation();

        // ✅ 청크 로딩 여부 확인 후 실행
        if (!$world->isChunkLoaded($location->getFloorX() >> 4, $location->getFloorZ() >> 4)) {
            return;
        }

        // 기본 좀비 제거 후 약간의 지연 후 커스텀 좀비 생성
        $pmmpZombie->flagForDespawn();
        $this->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($world, $location): void {
            $nbt = CompoundTag::create();
            (new Zombie($location, $nbt, $this))->spawnToAll();
        }), 1);
    }

    /** ✅ 엔티티가 공격받을 때 처리 */
    public function onEntityDamage(EntityDamageEvent $event): void {
        $entity = $event->getEntity();
        if ($entity instanceof Living) {
            if ($event instanceof EntityDamageByEntityEvent) {
                $damager = $event->getDamager();
                $this->handleDamageResponse($entity, $damager);
            }
        }
    }

    private function handleDamageResponse(Living $mob, $damager): void {
        if ($damager instanceof Player && $mob instanceof Zombie) {
        $mob->lookAt($damager->getPosition());

        // ✅ `subtract()`를 사용하지 않고 수동 계산
        $damagerPos = $damager->getPosition();
        $mobPos = $mob->getPosition();

        $direction = new Vector3(
            $damagerPos->getX() - $mobPos->getX(),
            $damagerPos->getY() - $mobPos->getY(),
            $damagerPos->getZ() - $mobPos->getZ()
        );
        $direction = $direction->normalize();

        $mob->setMotion($direction->multiply(0.25));
    }
}
    /** ✅ 랜덤 위치에 좀비 스폰 */
    private function spawnRandomZombies(): void {
        foreach ($this->getServer()->getWorldManager()->getWorlds() as $world) {
            foreach ($world->getPlayers() as $player) {
                $this->spawnZombieInFrontOfPlayer($player);
            }
        }
    }

    /*public function spawnZombieInFrontOfPlayer(Player $player): void {
    $this->getLogger()->info("플레이어 앞에 좀비 스폰 위치: " . $player->getPosition()->__toString());
    
    $direction = $player->getDirectionVector()->normalize()->multiply(2); // 2 블록 앞에 좀비 생성
    
    // ✅ 직접 Vector3 좌표 계산
    $spawnPosition = new Vector3(
        $player->getPosition()->getX() + $direction->getX(),
        $player->getPosition()->getY() + $direction->getY(),
        $player->getPosition()->getZ() + $direction->getZ()
    );
    $spawnPosition = $spawnPosition->add(0, 1, 0); // 좀비가 블록 안에 생성되지 않도록 Y 좌표 조정
    
    $this->spawnZombieAt($player->getWorld(), $spawnPosition);
}*/

    /** ✅ 청크가 로드된 경우에만 좀비 스폰 */
    public function spawnZombieAt(World $world, Vector3 $position): void {
        if (!$world->isChunkLoaded($position->getFloorX() >> 4, $position->getFloorZ() >> 4)) {
            return;
        }

        $location = new Location($position->x, $position->y, $position->z, $world, 0.0, 0.0);
        (new Zombie($location, CompoundTag::create(), $this))->spawnToAll();
    }
}
