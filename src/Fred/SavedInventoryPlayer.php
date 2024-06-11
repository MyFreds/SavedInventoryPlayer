<?php

namespace Fred;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\player\Player;
use pocketmine\item\Item;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\nbt\LittleEndianNbtSerializer;
use pocketmine\nbt\TreeRoot;

class SavedInventoryPlayer extends PluginBase implements Listener {

    public function onEnable(): void {
        $this->getLogger()->info("SavedInventoryPlayer enabled");
        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        // Ensure data folder exists
        if (!is_dir($this->getDataFolder() . "Inventory/")) {
            mkdir($this->getDataFolder() . "Inventory/", 0755, true);
        }
    }

    public function onDisable(): void {
        $this->getLogger()->info("SavedInventoryPlayer disabled");

        // Save inventories of all online players
        foreach ($this->getServer()->getOnlinePlayers() as $player) {
            $this->savePlayerInventory($player);
        }
    }

    /**
     * Event handler for player quit event
     * @param PlayerQuitEvent $event
     */
    public function onPlayerQuit(PlayerQuitEvent $event): void {
        $player = $event->getPlayer();
        $this->savePlayerInventory($player);
    }

    /**
     * Event handler for player join event
     * @param PlayerJoinEvent $event
     */
    public function onPlayerJoin(PlayerJoinEvent $event): void {
        $player = $event->getPlayer();
        $this->restorePlayerInventory($player);
    }

    private function getPlayerFileName(Player $player): string {
        $playerName = $player->getName();
        // Replace space with underscore
        $fileName = str_replace(" ", "_", $playerName);
        return $fileName . ".yml";
    }

    /**
     * Save player's inventory to a YAML file
     * @param Player $player
     */
    private function savePlayerInventory(Player $player): void {
        $inventory = $player->getInventory();
        $armorInventory = $player->getArmorInventory();
        $inventoryData = [
            'items' => [],
            'armor' => []
        ];

        // Save items
        foreach ($inventory->getContents() as $slot => $item) {
            $inventoryData['items'][$slot] = self::jsonSerialize($item);
        }

        // Save armor
        foreach ($armorInventory->getContents() as $slot => $item) {
            $inventoryData['armor'][$slot] = self::jsonSerialize($item);
        }

        $fileName = $this->getPlayerFileName($player); // Use adjusted file name
        $filePath = $this->getDataFolder() . "Inventory/" . $fileName;
        $config = new Config($filePath, Config::YAML);
        $config->setAll($inventoryData);
        $config->save();
    }

    /**
     * Restore player's inventory from a YAML file
     * @param Player $player
     */
    private function restorePlayerInventory(Player $player): void {
        $fileName = $this->getPlayerFileName($player); // Use adjusted file name
        $filePath = $this->getDataFolder() . "Inventory/" . $fileName;
        if (!file_exists($filePath)) {
            return;
        }

        $config = new Config($filePath, Config::YAML);
        $inventoryData = $config->getAll();
        $inventory = $player->getInventory();
        $armorInventory = $player->getArmorInventory();
        $inventory->clearAll();
        $armorInventory->clearAll();

        // Restore items
        foreach ($inventoryData['items'] as $slot => $itemData) {
            $inventory->setItem($slot, self::jsonDeserialize($itemData));
        }

        // Restore armor
        foreach ($inventoryData['armor'] as $slot => $itemData) {
            $armorInventory->setItem($slot, self::jsonDeserialize($itemData));
        }
    }

    private static function jsonSerialize(Item $item): string {
        return base64_encode((new LittleEndianNbtSerializer())->write(new TreeRoot($item->nbtSerialize())));
    }

    public static function jsonDeserialize(string $item): Item {
        $p = (new LittleEndianNbtSerializer())->read(base64_decode($item))->mustGetCompoundTag();
        return Item::nbtDeserialize($p);
    }
}
