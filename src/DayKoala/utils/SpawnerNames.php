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

namespace DayKoala\utils;

use pocketmine\network\mcpe\protocol\types\entity\EntityIds;
use pocketmine\utils\Config;
use pocketmine\utils\Filesystem;
use function yaml_parse;

final class SpawnerNames{

    private static array $names = [];

    private static array $defaultNames = [
        EntityIds::CHICKEN => 'Chicken',
        EntityIds::COW => 'Cow',
        EntityIds::PIG => 'Pig',
        EntityIds::SHEEP => 'Sheep',
        EntityIds::WOLF => 'Wolf',
        EntityIds::VILLAGER => 'Villager', // V1
        EntityIds::MOOSHROOM => 'Mooshroom',
        EntityIds::SQUID => 'Squid',
        EntityIds::RABBIT => 'Rabbit',
        EntityIds::BAT => 'Bat',
        EntityIds::IRON_GOLEM => 'Iron Golem',
        EntityIds::SNOW_GOLEM => 'Snow Golem',
        EntityIds::OCELOT => 'Ocelot',
        EntityIds::HORSE => 'Horse',
        EntityIds::DONKEY => 'Donkey',
        EntityIds::MULE => 'Mule',
        EntityIds::SKELETON_HORSE => 'Skeleton Horse',
        EntityIds::ZOMBIE_HORSE => 'Zombie Horse',
        EntityIds::POLAR_BEAR => 'Polar Bear',
        EntityIds::LLAMA => 'Llama',
        EntityIds::PARROT => 'Parrot',
        EntityIds::DOLPHIN => 'Dolphin',
        EntityIds::ZOMBIE => 'Zombie',
        EntityIds::CREEPER => 'Creeper',
        EntityIds::SKELETON => 'Skeleton',
        EntityIds::SPIDER => 'Spider',
        EntityIds::ZOMBIE_PIGMAN => 'Zombie Pigman',
        EntityIds::SLIME => 'Slime',
        EntityIds::ENDERMAN => 'Enderman',
        EntityIds::SILVERFISH => 'Silverfish',
        EntityIds::CAVE_SPIDER => 'Cave Spider',
        EntityIds::GHAST => 'Ghast',
        EntityIds::MAGMA_CUBE => 'Magma Cube',
        EntityIds::BLAZE => 'Blaze',
        EntityIds::ZOMBIE_VILLAGER => 'Zombie Villager', // V1
        EntityIds::WITCH => 'Witch',
        EntityIds::STRAY => 'Stray',
        EntityIds::HUSK => 'Husk',
        EntityIds::WITHER_SKELETON => 'Wither Skeleton',
        EntityIds::GUARDIAN => 'Guardian',
        EntityIds::ELDER_GUARDIAN => 'Elder Guardian',
        EntityIds::NPC => 'NPC',
        EntityIds::WITHER => 'Wither',
        EntityIds::ENDER_DRAGON => 'Ender Dragon',
        EntityIds::SHULKER => 'Shulker',
        EntityIds::ENDERMITE => 'Endermite',
        EntityIds::AGENT => 'Agent',
        EntityIds::VINDICATOR => 'Vindicator',
        EntityIds::PHANTOM => 'Phantom',
        EntityIds::RAVAGER => 'Ravanger',
        EntityIds::TRIPOD_CAMERA => 'Tripod Camera',
        EntityIds::XP_BOTTLE => 'XP Bottle',
        EntityIds::ENDER_CRYSTAL => 'Ender Crystal',
        EntityIds::FIREWORKS_ROCKET => 'Fireworks',
        EntityIds::TURTLE => 'Turtle',
        EntityIds::CAT => 'Cat',
        EntityIds::FIREBALL => 'Fireball',
        EntityIds::LIGHTNING_BOLT => 'Lightning Bolt',
        EntityIds::SMALL_FIREBALL => 'Small Fireball',
        EntityIds::EVOCATION_FANG => 'Evocation Fang',
        EntityIds::EVOCATION_ILLAGER => 'Evocation Illager',
        EntityIds::VEX => 'Vex',
        EntityIds::SALMON => 'Salmon',
        EntityIds::DROWNED => 'Drowned',
        EntityIds::TROPICALFISH => 'Tropicalfish',
        EntityIds::COD => 'Cod',
        EntityIds::PANDA => 'Panda',
        EntityIds::PILLAGER => 'Pillager',
        EntityIds::VILLAGER_V2 => 'Villager V2', // V2
        EntityIds::WANDERING_TRADER => 'Wandering Trader',
        EntityIds::ZOMBIE_VILLAGER_V2 => 'Zombie Villager V2', // V2
        EntityIds::FOX => 'Fox',
        EntityIds::BEE => 'Bee',
        EntityIds::PIGLIN => 'Piglin',
        EntityIds::HOGLIN => 'Hoglin',
        EntityIds::STRIDER => 'Strider',
        EntityIds::ZOGLIN => 'Zoglin',
        EntityIds::PIGLIN_BRUTE => 'Piglin Brute',
        EntityIds::BOAT => 'Goat',
        EntityIds::WARDEN => 'Warden',
        EntityIds::FROG => 'Frog',
        EntityIds::TADPOLE => 'Tadpole',
        EntityIds::ALLAY => 'Allay'
    ];

    public static function init(String $folder) : Void{
        $names = yaml_parse(Config::fixYAMLIndexes(Filesystem::fileGetContents($folder . 'Names.yml')));
        self::$names = $names === false ? self::$defaultNames : $names;
    }

    public static function hasName(string $id) : Bool{
        return isset(self::$names[$id]);
    }

    public static function getName(string $id) : String{
        return self::$names[$id] ?? self::getDefaultName($id);
    }

    public static function hasDefaultName(string $id) : Bool{
        return isset(self::$defaultNames[$id]);
    }

    public static function getDefaultName(string $id) : String{
        return self::$defaultNames[$id] ?? 'Unknown';
    }

    public static function getNames() : array{
        return self::$names;
    }

    private function __construct(){}

} 