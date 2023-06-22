<?php

/*
 *   _____       __                    _____
 *  / ___/____ _/ /____  ___________ _/ ___/____  ____ __      ______  ___  __________
 *  \__ \/ __ `/ //_/ / / / ___/ __ `/\__ \/ __ \/ __ `/ | /| / / __ \/ _ \/ ___/ ___/
 *  ___/ / /_/ / ,< / /_/ / /  / /_/ /___/ / /_/ / /_/ /| |/ |/ / / / /  __/ /  (__  )
 * /____/\__,_/_/|_|\__,_/_/   \__,_//____/ .___/\__,_/ |__/|__/_/ /_/\___/_/  /____/
 *                                        /_/
 *
 * This program is free software made for PocketMine-MP,
 * currently under the GNU Lesser General Public License published by
 * the Free Software Foundation, use according to the license terms.
 *
 * @author DayKoala
 * @link https://github.com/DayKoala/SakuraSpawners
 *
 *
*/

namespace DayKoala;

use pocketmine\data\bedrock\item\ItemTypeNames;
use pocketmine\data\bedrock\item\SavedItemData;
use pocketmine\data\bedrock\LegacyEntityIdToStringIdMap;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\EventPriority;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\event\world\ChunkLoadEvent;
use pocketmine\math\Facing;
use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\plugin\PluginBase;

use pocketmine\entity\EntityFactory;
use pocketmine\entity\EntityDataHelper as Helper;
use pocketmine\entity\EntitySizeInfo;
use pocketmine\entity\Location;

use pocketmine\scheduler\CancelTaskException;
use pocketmine\scheduler\ClosureTask;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;

use pocketmine\world\format\Chunk;
use pocketmine\world\format\io\GlobalItemDataHandlers;
use pocketmine\world\particle\MobSpawnParticle;
use pocketmine\world\World;

use pocketmine\nbt\tag\CompoundTag;

use pocketmine\block\VanillaBlocks;
use pocketmine\block\tile\MonsterSpawner;

use pocketmine\item\StringToItemParser;
use pocketmine\item\Item;
use pocketmine\item\ItemIdentifier;
use pocketmine\item\ItemTypeIds;

use DayKoala\utils\SpawnerNames;

use DayKoala\entity\SpawnerEntity;

use DayKoala\item\SpawnEgg;

use DayKoala\command\SakuraSpawnersCommand;
use function array_search;
use function floor;
use function mt_rand;
use function str_replace;
use function strtolower;

final class SakuraSpawners extends PluginBase{

    public const TAG_ENTITY_NAME = 'entity.name';
    public const TAG_SPAWNER_NAME = 'spawner.name';

    public const TAG_SPAWNER_STACK_DISTANCE = 'spawner.stack.distance';

    public const TAG_SPAWNER_DROPS = 'spawner.drops';
    public const TAG_SPAWNER_XP = 'spawner.xp';

    public const TAG_SPAWNER_HEIGHT = 'spawner.height';
    public const TAG_SPAWNER_WIDTH = 'spawner.width';

    private static SakuraSpawners|null $instance = null;

    public static function getInstance() : ?self{
        return self::$instance;
    }

    private array $settings;

    private array $drops;
    private array $size;

    protected function onLoad() : Void{
        self::$instance = $this;
    }

    protected function onEnable() : Void{
        $this->saveResource('Names.yml');
        $this->saveResource('Settings.yml');

        $this->settings = (new Config($this->getDataFolder() .'Settings.yml', Config::YAML))->getAll();

        $this->writeSpawnerBlock();
        $this->writeSpawnerItem();
        $this->writeSpawnerSettings();

        $this->getServer()->getCommandMap()->register('SakuraSpawners', new SakuraSpawnersCommand($this));

        $this->getServer()->getPluginManager()->registerEvent(ChunkLoadEvent::class, function(ChunkLoadEvent $event) : void{
            foreach($event->getChunk()->getTiles() as $tile){
                if($tile instanceof MonsterSpawner && (new \ReflectionProperty($tile, "entityTypeId"))->getValue($tile) !== ":"){
                    $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function() use ($tile) : void{
                        $this->updateSpawner($tile);
                    }), mt_rand(MonsterSpawner::DEFAULT_MIN_SPAWN_DELAY, MonsterSpawner::DEFAULT_MAX_SPAWN_DELAY));
                }
            }
        }, EventPriority::NORMAL, $this);
        $this->getServer()->getPluginManager()->registerEvent(BlockPlaceEvent::class, function(BlockPlaceEvent $event) : void{
            if(($entityTypeId = $event->getItem()->getNamedTag()->getString("sakuraspawmers:entityTypeId", ":")) !== ":"){
                $world = $event->getPlayer()->getWorld();
                foreach($event->getTransaction()->getBlocks() as [$x, $y, $z, $block]){
                    if($block instanceof \pocketmine\block\MonsterSpawner){
                        $this->getScheduler()->scheduleDelayedTask(new ClosureTask(function() use ($z, $y, $x, $world, $entityTypeId) : void{
                            $tile = $world->getTileAt($x, $y, $z);
                            if(!($tile instanceof MonsterSpawner)){
                                return;
                            }
                            (new \ReflectionProperty($tile, "entityTypeId"))->setValue($tile, $entityTypeId);
                            $spawnDelay = mt_rand(MonsterSpawner::DEFAULT_MIN_SPAWN_DELAY, MonsterSpawner::DEFAULT_MAX_SPAWN_DELAY);
                            (new \ReflectionProperty($tile, "spawnDelay"))->setValue($tile, $spawnDelay);
                            if($entityTypeId === ":"){
                                $this->getScheduler()->scheduleRepeatingTask(new ClosureTask(function() use ($tile) : void{
                                    $this->updateSpawner($tile);
                                }), $spawnDelay);
                            }
                            $world->setBlockAt($x, $y, $z, $world->getBlockAt($x, $y, $z));
                        }), 1);
                    }
                }
            }
        }, EventPriority::MONITOR, $this);
        $this->getServer()->getPluginManager()->registerEvent(PlayerInteractEvent::class, function(PlayerInteractEvent $event) : void{
            if($event->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK){
                return;
            }
            $item = $event->getItem();
            $entityTypeId = match($item->getTypeId()){
                ItemTypeIds::SQUID_SPAWN_EGG => EntityIds::SQUID,
                ItemTypeIds::VILLAGER_SPAWN_EGG => EntityIds::VILLAGER_V2,
                ItemTypeIds::ZOMBIE_SPAWN_EGG => EntityIds::ZOMBIE,
                default => null
            };
            if($entityTypeId !== null){
                $returnedItems = [];
                (new SpawnEgg(new ItemIdentifier($item->getTypeId()), $item->getName(), $entityTypeId))->onInteractBlock($event->getPlayer(), $event->getBlock()->getSide($event->getFace()), $event->getBlock(), $event->getFace(), $event->getTouchVector(), $returnedItems);
                $event->cancel();
            }
        }, EventPriority::HIGH, $this);
    }

    public function updateSpawner(MonsterSpawner $tile) : void{
        if($tile->isClosed()){
            throw new CancelTaskException();
        }

        $pos = $tile->getPosition();

        $spawnRange = (new \ReflectionProperty($tile, "spawnRange"))->getValue($tile);
        $minX = ((int) floor($pos->x - $spawnRange)) >> Chunk::COORD_BIT_SIZE;
        $maxX = ((int) floor($pos->x + $spawnRange)) >> Chunk::COORD_BIT_SIZE;
        $minZ = ((int) floor($pos->z - $spawnRange)) >> Chunk::COORD_BIT_SIZE;
        $maxZ = ((int) floor($pos->z + $spawnRange)) >> Chunk::COORD_BIT_SIZE;

        $target = null;
        $entityTypeId = (new \ReflectionProperty($tile, "entityTypeId"))->getValue($tile);
        for($x = $minX; $x <= $maxX; $x++){
            for($z = $minZ; $z <= $maxZ; $z++){
                if(!$pos->getWorld()->isChunkLoaded($x, $z)){
                    continue;
                }
                foreach($pos->getWorld()->getChunkEntities($x, $z) as $entity){
                    if(!$entity instanceof SpawnerEntity or !$entity->isAlive() or $entity->isFlaggedForDespawn()){
                        continue;
                    }
                    $minY = (int) floor($pos->y - $entity->getPosition()->y);
                    if($entityTypeId !== $entity->getModifiedNetworkTypeId() or $spawnRange < $minY){
                        continue;
                    }
                    $target = $entity;
                    break 3;
                }
            }
        }

        if($target === null){
            $position = $tile->getPosition();
            for($attempts = 0; $attempts < (new \ReflectionProperty($tile, "spawnPerAttempt"))->getValue($tile); $attempts++){
                $pos = $position->add(mt_rand(-$spawnRange, $spawnRange), mt_rand(-1, 1), mt_rand(-$spawnRange, $spawnRange));
                if(
                    $position->getWorld()->getBlock($pos)->isSolid() and
                    !$position->getWorld()->getBlock($pos)->canBeFlowedInto() or
                    !$position->getWorld()->getBlock($pos->subtract(0, 1, 0))->isSolid()
                ){
                    continue;
                }
                $entity = new SpawnerEntity(Location::fromObject($pos, $position->getWorld()), $entityTypeId);
                $entity->spawnToAll();
                $position->getWorld()->addParticle($pos, new MobSpawnParticle((int) $entity->getSize()->getWidth(), (int) $entity->getSize()->getHeight()));
                break;
            }
        }else $target->addStackSize(1);
    }

    protected function onDisable() : Void{
        if(empty($this->settings)){
           return;
        }
        $settings = new Config($this->getDataFolder() .'Settings.yml', Config::YAML);
        $settings->setAll($this->settings);
        $settings->save();
    }

    public function getSettings() : array{
        return $this->settings ?? [];
    }

    public function getDefaultEntityName() : String{
        return isset($this->settings[self::TAG_ENTITY_NAME]) ? (string) $this->settings[self::TAG_ENTITY_NAME] : "{name} x{stack}";
    }

    public function setDefaultEntityName(String $name) : Void{
        $this->settings[self::TAG_ENTITY_NAME] = $name;
    }

    public function getDefaultSpawnerName() : String{
        return isset($this->settings[self::TAG_SPAWNER_NAME]) ? (string) $this->settings[self::TAG_SPAWNER_NAME] : "{name} Spawner";
    }

    public function setDefaultSpawnerName(String $name) : Void{
        $this->settings[self::TAG_SPAWNER_NAME] = $name;
    }

    public function getSpawnerStackDistance() : Int{
        return isset($this->settings[self::TAG_SPAWNER_STACK_DISTANCE]) ? (int) $this->settings[self::TAG_SPAWNER_STACK_DISTANCE] : 4;
    }

    public function setSpawnerStackDistance(Int $distance) : Void{
        $this->settings[self::TAG_SPAWNER_STACK_DISTANCE] = $distance < 0 ? 0 : $distance;
    }

    public function hasSpawner(Int $id) : Bool{
        return isset($this->settings[$id]);
    }

    public function getSpawner(Int $id) : array{
        return $this->settings[$id] ?? [];
    }

    public function hasSpawnerXp(Int $id) : Bool{
        return isset($this->settings[$id], $this->settings[$id][self::TAG_SPAWNER_XP]);
    }

    public function getSpawnerXp(Int $id) : Int{
        return isset($this->settings[$id], $this->settings[$id][self::TAG_SPAWNER_XP]) ? (int) $this->settings[$id][self::TAG_SPAWNER_XP] : 0;
    }

    public function setSpawnerXp(Int $id, Int $amount) : Void{
        $this->settings[$id][self::TAG_SPAWNER_XP] = $amount < 0 ? 0 : $amount;
    }

    public function hasSpawnerDrops(Int $id) : Bool{
        return isset($this->drops[$id]);
    }

    public function getSpawnerDrops(Int $id) : array{
        return $this->drops[$id] ?? [];
    }

    public function hasSpawnerDrop(Int $id, Item $item) : Bool{
        return isset($this->drops[$id], $this->drops[$id][$item->__toString()]);
    }

    public function addSpawnerDrop(Int $id, Item $item) : Void{
        $this->settings[$id][self::TAG_SPAWNER_DROPS][$item->__toString()] = ["name" => StringToItemParser::getInstance()->lookupAliases($item)[0], "count" => $item->getCount()];
        $this->drops[$id][$item->__toString()] = $item;
    }

    public function removeSpawnerDrop(Int $id, Item $item) : Void{
        if(isset($this->drops[$id], $this->drops[$id][$item->__toString()])) unset($this->drops[$id][$item->__toString()]);
        if(isset($this->settings[$id], $this->settings[$id][self::TAG_SPAWNER_DROPS], $this->settings[$id][self::TAG_SPAWNER_DROPS][$item->__toString()])) unset($this->settings[$id][self::TAG_SPAWNER_DROPS][$item->__toString()]);
    }

    public function hasSpawnerSize(Int $id) : Bool{
        return isset($this->size[$id]);
    }

    public function getSpawnerSize(Int $id) : EntitySizeInfo{
        return $this->size[$id] ?? new EntitySizeInfo(1.3, 1.3);
    }

    public function setSpawnerSize(Int $id, Float $height, Float $width) : Void{
        $this->settings[$id][self::TAG_SPAWNER_HEIGHT] = $height = $height < 0.5 ? 0.5 : $height;
        $this->settings[$id][self::TAG_SPAWNER_WIDTH] = $width = $width < 0.5 ? 0.5 : $width;
        $this->size[$id] = new EntitySizeInfo($height, $width);
    }

    private function writeSpawnerBlock() : Void{
        EntityFactory::getInstance()->register(SpawnerEntity::class, function(World $world, CompoundTag $nbt) : SpawnerEntity{
            return new SpawnerEntity(Helper::parseLocation($nbt, $world), $nbt->getString("sakuraspawners:entityTypeId", SpawnerEntity::getNetworkTypeId()), $nbt);
        }, ['SpawnerEntity']);
    }

    private function writeSpawnerItem() : Void{
        SpawnerNames::init($this->getDataFolder());

        $serializer = GlobalItemDataHandlers::getSerializer();
        $deserializer = GlobalItemDataHandlers::getDeserializer();
        $parser = StringToItemParser::getInstance();

        foreach(SpawnerNames::getNames() as $entityId => $name){
            $clean = TextFormat::clean($name);

            $itemTypeName = match($entityId){
                EntityIds::ALLAY => ItemTypeNames::ALLAY_SPAWN_EGG,
                EntityIds::AGENT => ItemTypeNames::AGENT_SPAWN_EGG,
                EntityIds::AXOLOTL => ItemTypeNames::AXOLOTL_SPAWN_EGG,
                EntityIds::BAT => ItemTypeNames::BAT_SPAWN_EGG,
                EntityIds::BEE => ItemTypeNames::BEE_SPAWN_EGG,
                EntityIds::BLAZE => ItemTypeNames::BLAZE_SPAWN_EGG,
                EntityIds::CAMEL => ItemTypeNames::CAMEL_SPAWN_EGG,
                EntityIds::CAT => ItemTypeNames::CAT_SPAWN_EGG,
                EntityIds::CAVE_SPIDER => ItemTypeNames::CAVE_SPIDER_SPAWN_EGG,
                EntityIds::CHICKEN => ItemTypeNames::CHICKEN_SPAWN_EGG,
                EntityIds::COD => ItemTypeNames::COD_SPAWN_EGG,
                EntityIds::COW => ItemTypeNames::COW_SPAWN_EGG,
                EntityIds::CREEPER => ItemTypeNames::CREEPER_SPAWN_EGG,
                EntityIds::DOLPHIN => ItemTypeNames::DOLPHIN_SPAWN_EGG,
                EntityIds::DONKEY => ItemTypeNames::DONKEY_SPAWN_EGG,
                EntityIds::DROWNED => ItemTypeNames::DROWNED_SPAWN_EGG,
                EntityIds::ELDER_GUARDIAN => ItemTypeNames::ELDER_GUARDIAN_SPAWN_EGG,
                EntityIds::ENDER_DRAGON => ItemTypeNames::ENDER_DRAGON_SPAWN_EGG,
                EntityIds::ENDERMAN => ItemTypeNames::ENDERMAN_SPAWN_EGG,
                EntityIds::ENDERMITE => ItemTypeNames::ENDERMITE_SPAWN_EGG,
                EntityIds::EVOCATION_ILLAGER => ItemTypeNames::EVOKER_SPAWN_EGG,
                EntityIds::FOX => ItemTypeNames::FOX_SPAWN_EGG,
                EntityIds::FROG => ItemTypeNames::FROG_SPAWN_EGG,
                EntityIds::GHAST => ItemTypeNames::GHAST_SPAWN_EGG,
                EntityIds::GLOW_SQUID => ItemTypeNames::GLOW_SQUID_SPAWN_EGG,
                EntityIds::GOAT => ItemTypeNames::GOAT_SPAWN_EGG,
                EntityIds::GUARDIAN => ItemTypeNames::GUARDIAN_SPAWN_EGG,
                EntityIds::HOGLIN => ItemTypeNames::HOGLIN_SPAWN_EGG,
                EntityIds::HORSE => ItemTypeNames::HORSE_SPAWN_EGG,
                EntityIds::HUSK => ItemTypeNames::HUSK_SPAWN_EGG,
                EntityIds::IRON_GOLEM => ItemTypeNames::IRON_GOLEM_SPAWN_EGG,
                EntityIds::LLAMA => ItemTypeNames::LLAMA_SPAWN_EGG,
                EntityIds::MAGMA_CUBE => ItemTypeNames::MAGMA_CUBE_SPAWN_EGG,
                EntityIds::MOOSHROOM => ItemTypeNames::MOOSHROOM_SPAWN_EGG,
                EntityIds::MULE => ItemTypeNames::MULE_SPAWN_EGG,
                EntityIds::NPC => ItemTypeNames::NPC_SPAWN_EGG,
                EntityIds::OCELOT => ItemTypeNames::OCELOT_SPAWN_EGG,
                EntityIds::PANDA => ItemTypeNames::PANDA_SPAWN_EGG,
                EntityIds::PARROT => ItemTypeNames::PARROT_SPAWN_EGG,
                EntityIds::PHANTOM => ItemTypeNames::PHANTOM_SPAWN_EGG,
                EntityIds::PIG => ItemTypeNames::PIG_SPAWN_EGG,
                EntityIds::PIGLIN_BRUTE => ItemTypeNames::PIGLIN_BRUTE_SPAWN_EGG,
                EntityIds::PIGLIN => ItemTypeNames::PIGLIN_SPAWN_EGG,
                EntityIds::PILLAGER => ItemTypeNames::PILLAGER_SPAWN_EGG,
                EntityIds::POLAR_BEAR => ItemTypeNames::POLAR_BEAR_SPAWN_EGG,
                EntityIds::PUFFERFISH => ItemTypeNames::PUFFERFISH_SPAWN_EGG,
                EntityIds::RABBIT => ItemTypeNames::RABBIT_SPAWN_EGG,
                EntityIds::RAVAGER => ItemTypeNames::RAVAGER_SPAWN_EGG,
                EntityIds::SALMON => ItemTypeNames::SALMON_SPAWN_EGG,
                EntityIds::SHEEP => ItemTypeNames::SHEEP_SPAWN_EGG,
                EntityIds::SHULKER => ItemTypeNames::SHULKER_SPAWN_EGG,
                EntityIds::SILVERFISH => ItemTypeNames::SILVERFISH_SPAWN_EGG,
                EntityIds::SKELETON => ItemTypeNames::SKELETON_SPAWN_EGG,
                EntityIds::SKELETON_HORSE => ItemTypeNames::SKELETON_HORSE_SPAWN_EGG,
                EntityIds::SLIME => ItemTypeNames::SLIME_SPAWN_EGG,
                EntityIds::SNOW_GOLEM => ItemTypeNames::SNOW_GOLEM_SPAWN_EGG,
                EntityIds::SPIDER => ItemTypeNames::SPIDER_SPAWN_EGG,
                EntityIds::STRAY => ItemTypeNames::STRAY_SPAWN_EGG,
                EntityIds::STRIDER => ItemTypeNames::STRIDER_SPAWN_EGG,
                EntityIds::TADPOLE => ItemTypeNames::TADPOLE_SPAWN_EGG,
                EntityIds::TRADER_LLAMA => ItemTypeNames::TRADER_LLAMA_SPAWN_EGG,
                EntityIds::TROPICALFISH => ItemTypeNames::TROPICAL_FISH_SPAWN_EGG,
                EntityIds::TURTLE => ItemTypeNames::TURTLE_SPAWN_EGG,
                EntityIds::VEX => ItemTypeNames::VEX_SPAWN_EGG,
                EntityIds::VINDICATOR => ItemTypeNames::VINDICATOR_SPAWN_EGG,
                EntityIds::WANDERING_TRADER => ItemTypeNames::WANDERING_TRADER_SPAWN_EGG,
                EntityIds::WARDEN => ItemTypeNames::WARDEN_SPAWN_EGG,
                EntityIds::WITCH => ItemTypeNames::WITCH_SPAWN_EGG,
                EntityIds::WITHER_SKELETON => ItemTypeNames::WITHER_SKELETON_SPAWN_EGG,
                EntityIds::WITHER => ItemTypeNames::WITHER_SPAWN_EGG,
                EntityIds::WOLF => ItemTypeNames::WOLF_SPAWN_EGG,
                EntityIds::ZOGLIN => ItemTypeNames::ZOGLIN_SPAWN_EGG,
                EntityIds::ZOMBIE_HORSE => ItemTypeNames::ZOMBIE_HORSE_SPAWN_EGG,
                EntityIds::ZOMBIE_PIGMAN => ItemTypeNames::ZOMBIE_PIGMAN_SPAWN_EGG,
                EntityIds::ZOMBIE_VILLAGER_V2 => ItemTypeNames::ZOMBIE_VILLAGER_SPAWN_EGG,
                default => null
            };
            $legacyId = array_search($entityId, LegacyEntityIdToStringIdMap::getInstance()->getLegacyToStringMap());
            if($itemTypeName !== null){
                $serializer->map($egg = new SpawnEgg(new ItemIdentifier(ItemTypeIds::newId()), $clean . " Egg", $entityId), fn() => new SavedItemData($itemTypeName));
                $deserializer->map($itemTypeName, fn() => clone $egg);
                $parser->register($itemTypeName, fn() => clone $egg);
                if($legacyId !== false){
                    $parser->register($itemTypeName . ":" . $legacyId, fn() => clone $egg);
                }
            }

            $spawner = VanillaBlocks::MONSTER_SPAWNER()->asItem()->setCustomName(str_replace("{name}", $name, $this->getDefaultSpawnerName()));
            $spawner->getNamedTag()->setString("sakuraspawmers:entityTypeId", $entityId);
            if($legacyId !== false){
                $parser->register("mob_spawner:" . $legacyId, fn() => clone $spawner);
                $parser->register("monster_spawner:" . $legacyId, fn() => clone $spawner);
            }
            $parser->register(str_replace(" ", "_", strtolower($clean)) . "_spawner", fn() => clone $spawner);
        }
    }

    private function writeSpawnerSettings() : Void{
        if(empty($this->settings)){
           return;
        }
        foreach($this->settings as $id => $data){
            if(isset($data[self::TAG_SPAWNER_DROPS])){
                foreach($data[self::TAG_SPAWNER_DROPS] as $name => $item) $this->drops[$id][$name] = isset($item["id"]) ?
                    GlobalItemDataHandlers::getDeserializer()->deserializeStack(GlobalItemDataHandlers::getUpgrader()->upgradeItemTypeDataInt($item["id"], $item["meta"] ?? 0, $item["count"] ?? 1, null)) :
                    StringToItemParser::getInstance()->parse($item["name"])->setCount($item["count"] ?? 1);
            }
            if(isset($data[self::TAG_SPAWNER_HEIGHT], $data[self::TAG_SPAWNER_WIDTH])) $this->size[$id] = new EntitySizeInfo((float) $data[self::TAG_SPAWNER_HEIGHT], (float) $data[self::TAG_SPAWNER_WIDTH]);
        }
    }

}