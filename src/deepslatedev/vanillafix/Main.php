<?php

declare(strict_types=1);

namespace deepslatedev\vanillafix;

use pocketmine\block\Block;
use pocketmine\block\VanillaBlocks;
use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\data\bedrock\block\BlockStateData;
use pocketmine\data\bedrock\block\convert\BlockStateReader;
use pocketmine\item\StringToItemParser;
use pocketmine\network\mcpe\convert\TypeConverter;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use pocketmine\world\format\io\GlobalBlockStateHandlers;

final class Main extends PluginBase{
    private const FAMILIES = [
        "_shelf" => "oak_planks",
        "_chest" => "chest",
        "_statue" => "air",
        "_propagule" => "air",
        "_egg" => "air",
        "_spawn" => "air",
        "_bush" => "air",
        "bush" => "air",
        "_grass" => "air",
        "flowers" => "air",
        "_eyeblossom" => "air",
        "_dandelion" => "air",
        "_moss" => "air",
        "_vein" => "air",
        "_carpet" => "white_carpet",
        "_stained_glass_pane" => "glass_pane",
        "_glass_pane" => "glass_pane",
        "_stained_glass" => "glass",
        "_glass" => "glass",
        "_wool" => "white_wool",
        "_concrete_powder" => "sand",
        "_concrete" => "white_concrete",
        "_glazed_terracotta" => "terracotta",
        "_terracotta" => "terracotta",
        "_leaves" => "oak_leaves",
        "_log" => "oak_log",
        "_wood" => "oak_log",
        "_stem" => "oak_log",
        "_hyphae" => "oak_log",
        "_planks" => "oak_planks",
        "_stairs" => "oak_stairs",
        "_slab" => "oak_slab",
        "_wall" => "cobblestone_wall",
        "_fence_gate" => "oak_fence_gate",
        "_fence" => "oak_fence",
        "_ore" => "stone",
        "_bricks" => "stone_bricks",
        "_tiles" => "stone_bricks",
        "_block" => "stone",
        "_door" => "air",
        "_trapdoor" => "air",
        "_button" => "air",
        "_pressure_plate" => "air",
        "_sign" => "air",
        "_banner" => "air",
        "_candle" => "air",
        "_torch" => "air",
        "_coral" => "air",
        "_coral_fan" => "air",
        "_sapling" => "air",
        "_flower" => "air",
        "_vines" => "air",
        "_rail" => "air",
    ];

    private array $mapped = [];
    private array $hits = [];
    private array $cache = [];

    protected function onLoad(): void{
        $this->saveDefaultConfig();
        $deserializer = GlobalBlockStateHandlers::getDeserializer();
        $targets = [];
        foreach((array) $this->getConfig()->get("replacements", []) as $id => $replacement){
            $id = self::normalise((string) $id);
            if($id !== ""){
                $targets[$id] = trim((string) $replacement);
            }
        }
        if((bool) $this->getConfig()->get("auto-detect", true)){
            foreach($this->unsupported() as $id){
                $targets[$id] ??= self::guess($id, (string) $this->getConfig()->get("fallback", "stone"));
            }
        }
        $fallback = (string) $this->getConfig()->get("fallback", "stone");
        foreach($targets as $id => $replacement){
            if($deserializer->getDeserializerForId($id) !== null){
                continue;
            }
            if(StringToItemParser::getInstance()->parse($replacement) === null){
                $this->getLogger()->warning("Unknown replacement \"$replacement\" for $id, using $fallback");
                $replacement = $fallback;
            }
            $deserializer->map($id, fn(BlockStateReader $reader): Block => $this->resolve($id, $replacement, $reader));
            $this->mapped[$id] = $replacement;
        }
        $this->getLogger()->info(count($this->mapped) . " unsupported block type(s) will be replaced with a vanilla equivalent");
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool{
        if(strtolower($args[0] ?? "") === "scan"){
            $missing = $this->unsupported();
            $sender->sendMessage(TextFormat::AQUA . count($missing) . TextFormat::GRAY . " vanilla block type(s) are not supported by this server software:");
            foreach(array_chunk($missing, 6) as $chunk){
                $sender->sendMessage(TextFormat::GRAY . implode(", ", array_map(static fn(string $id) => str_replace("minecraft:", "", $id), $chunk)));
            }
            return true;
        }
        arsort($this->hits);
        $total = array_sum($this->hits);
        $sender->sendMessage(TextFormat::AQUA . "VanillaFix " . TextFormat::GRAY . "covers " . TextFormat::WHITE . count($this->mapped) . TextFormat::GRAY . " block type(s) and repaired " . TextFormat::WHITE . $total . TextFormat::GRAY . " block(s) since startup.");
        foreach(array_slice($this->hits, 0, 10, true) as $id => $count){
            $sender->sendMessage(TextFormat::GRAY . " - " . TextFormat::WHITE . str_replace("minecraft:", "", $id) . TextFormat::GRAY . " x" . $count . " -> " . TextFormat::GREEN . $this->mapped[$id]);
        }
        return true;
    }

    private function unsupported(): array{
        $deserializer = GlobalBlockStateHandlers::getDeserializer();
        $missing = [];
        try{
            $states = TypeConverter::getInstance()->getBlockTranslator()->getBlockStateDictionary()->getStates();
        }catch(\Throwable){
            return [];
        }
        foreach($states as $state){
            $id = $state->getStateName();
            if(!isset($missing[$id]) && $deserializer->getDeserializerForId($id) === null){
                $missing[$id] = true;
            }
        }
        $ids = array_keys($missing);
        sort($ids);
        return $ids;
    }

    private static function guess(string $id, string $fallback): string{
        $name = str_replace("minecraft:", "", $id);
        foreach(self::FAMILIES as $suffix => $replacement){
            if(str_ends_with($name, $suffix)){
                return $replacement;
            }
        }
        return $fallback;
    }

    private static function normalise(string $id): string{
        $id = strtolower(trim($id));
        if($id === ""){
            return "";
        }
        return str_contains($id, ":") ? $id : "minecraft:" . $id;
    }

    private function resolve(string $id, string $replacement, BlockStateReader $reader): Block{
        $this->consumeStates($reader);
        $this->hits[$id] = ($this->hits[$id] ?? 0) + 1;
        if(!isset($this->cache[$id])){
            $item = StringToItemParser::getInstance()->parse($replacement);
            $block = $item?->getBlock();
            if($block === null || ($block->getTypeId() === VanillaBlocks::AIR()->getTypeId() && strtolower($replacement) !== "air")){
                $this->getLogger()->warning("Unknown replacement \"$replacement\" for $id, using air");
                $block = VanillaBlocks::AIR();
            }
            $this->cache[$id] = $block;
        }
        return clone $this->cache[$id];
    }

    private function consumeStates(BlockStateReader $reader): void{
        static $property = null;
        try{
            if($property === null){
                $property = new \ReflectionProperty(BlockStateReader::class, "data");
                $property->setAccessible(true);
            }
            $data = $property->getValue($reader);
            if($data instanceof BlockStateData){
                foreach($data->getStates() as $name => $unused){
                    $reader->ignored((string) $name);
                }
            }
        }catch(\Throwable){
        }
    }
}
