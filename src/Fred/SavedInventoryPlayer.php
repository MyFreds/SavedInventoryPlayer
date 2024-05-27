<?php

namespace Fred;

use pocketmine\plugin\PluginBase;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerQuitEvent;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\player\Player;
use pocketmine\item\Item;
use pocketmine\block\BlockTypeIds;
use pocketmine\inventory\ArmorInventory;
use pocketmine\item\Armor;
use pocketmine\item\StringToItemParser;
use pocketmine\item\Tool;
use pocketmine\utils\Config;
use pocketmine\utils\TextFormat;
use pocketmine\item\enchantment\Enchantment;
use pocketmine\item\enchantment\EnchantmentInstance;
use pocketmine\item\enchantment\VanillaEnchantments;
use pocketmine\item\enchantment\StringToEnchantmentParser;
use pocketmine\lang\Translatable;
use pocketmine\Server;

class SavedInventoryPlayer extends PluginBase implements Listener {

    /** @var Enchantment[] */
    private array $enchantments = [];

    public function onEnable(): void {
        $this->getLogger()->info(TextFormat::GREEN . "SaveInventoryPlayer enabled");
        $this->getServer()->getPluginManager()->registerEvents($this, $this);

        // Ensure data folder exists
        if (!is_dir($this->getDataFolder() . "Inventory/")) {
            mkdir($this->getDataFolder() . "Inventory/", 0755, true);
        }
      
        $this->initializeEnchantments();
    }

    private function initializeEnchantments(): void {
        $enchantmentNames = VanillaEnchantments::getAll();
        foreach ($enchantmentNames as $enchantmentName) {
            if (is_string($enchantmentName)) {
                $enchantment = StringToEnchantmentParser::getInstance()->parse($enchantmentName);
                if ($enchantment !== null) {
                    $this->enchantments[$enchantmentName] = $enchantment;
                } else {
                    $this->getLogger()->warning("Failed to parse enchantment: $enchantmentName");
                }
            }
        }
    }

    public function onDisable(): void {
        $this->getLogger()->info(TextFormat::RED . "SaveInventoryPlayer disabled");

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

    /**
     * Save player's inventory to a YAML file
     * @param Player $player
     */
    private function savePlayerInventory(Player $player): void {
        $inventory = $player->getInventory();
        $armorInventory = $player->getArmorInventory();
        $inventoryData = [
            'items' => [],
            'tools' => [],
            'armor' => []
        ];

        // Save armor
        for ($i = 0; $i < $armorInventory->getSize(); $i++) {
            $item = $armorInventory->getItem($i);
            if ($item->getTypeId() !== BlockTypeIds::AIR) {
                $itemName = $item->getName();
                $itemData = [
                    'slot-inventory' => $i,
                    'amount' => $item->getCount(),
                    'enchantments' => $this->getEnchantmentsInfo($item)
                ];
                $inventoryData['armor'][] = [
                    $itemName => $itemData
                ];
            }
        }

        // Save items and tools
        foreach ($inventory->getContents() as $slot => $item) {
            $itemName = $item->getName();
            $itemData = [
                'slot-inventory' => $slot,
                'amount' => $item->getCount()
            ];

            if ($item instanceof Tool) {
                $itemData['enchantments'] = $this->getEnchantmentsInfo($item);
                $inventoryData['tools'][] = [
                    $itemName => $itemData
                ];
            } else {
                $inventoryData['items'][] = [
                    $itemName => $itemData
                ];
            }
        }

        $filePath = $this->getDataFolder() . "Inventory/" . $player->getName() . ".yml";
        $config = new Config($filePath, Config::YAML);
        $config->setAll($inventoryData);
        $config->save();
    }

    /**
     * Restore player's inventory from a YAML file
     * @param Player $player
     */
    private function restorePlayerInventory(Player $player): void {
        $filePath = $this->getDataFolder() . "Inventory/" . $player->getName() . ".yml";
        if (!file_exists($filePath)) {
            return;
        }

        $config = new Config($filePath, Config::YAML);
        $inventoryData = $config->getAll();
        $inventory = $player->getInventory();
        $armorInventory = $player->getArmorInventory();
        $inventory->clearAll();
        $armorInventory->clearAll();

        // Restore armor
        foreach ($inventoryData['armor'] as $armorData) {
            foreach ($armorData as $itemName => $data) {
                $item = StringToItemParser::getInstance()->parse($itemName);
                $item->setCount($data['amount']);

                if (isset($data['enchantments'])) {
                    foreach ($data['enchantments'] as $enchantmentData) {
                        $enchantmentName = $enchantmentData['name'];
                        $enchantmentLevel = $enchantmentData['level'];

                        if ($enchantmentName !== 'no_enchantments') {
                            $enchantment = StringToEnchantmentParser::getInstance()->parse(strtolower($enchantmentName));
                            if ($enchantment !== null) {
                                $item->addEnchantment(new EnchantmentInstance($enchantment, (int)$enchantmentLevel));
                            }
                        }
                    }
                }

                $armorInventory->setItem($data['slot-inventory'], $item);
            }
        }

        // Restore items
        foreach ($inventoryData['items'] as $itemData) {
            foreach ($itemData as $itemName => $data) {
                $item = StringToItemParser::getInstance()->parse($itemName);
                $item->setCount($data['amount']);
                $inventory->setItem($data['slot-inventory'], $item);
            }
        }

        // Restore tools
        foreach ($inventoryData['tools'] as $toolData) {
            foreach ($toolData as $toolName => $data) {
                $tool = StringToItemParser::getInstance()->parse($toolName);
                $tool->setCount($data['amount']);

                if (isset($data['enchantments'])) {
                    foreach ($data['enchantments'] as $enchantmentData) {
                        $enchantmentName = $enchantmentData['name'];
                        $enchantmentLevel = $enchantmentData['level'];

                        if ($enchantmentName !== 'no_enchantments') {
                            $enchantment = StringToEnchantmentParser::getInstance()->parse(strtolower($enchantmentName));
                            if ($enchantment !== null) {
                                $tool->addEnchantment(new EnchantmentInstance($enchantment, (int)$enchantmentLevel));
                            }
                        }
                    }
                }

                $inventory->setItem($data['slot-inventory'], $tool);
            }
        }
    }

    public function getEnchantmentByName(string $name): ?Enchantment {
        return $this->enchantments[$name] ?? null;
    }

    /**
     * Get enchantments information from an item
     * @param Item $item
     * @return array
     */
    private function getEnchantmentsInfo(Item $item): array {
        $enchantments = $item->getEnchantments();
        $result = [];

        foreach ($enchantments as $enchantmentInstance) {
            $enchantmentName = $enchantmentInstance->getType()->getName();
            if ($enchantmentName instanceof Translatable) {
                $enchantmentName = $enchantmentName->getText();
            }

            $result[] = [
                'name' => Server::getInstance()->getLanguage()->translateString($enchantmentName),
                'level' => $enchantmentInstance->getLevel()
            ];
        }

        if (empty($result)) {
            $result[] = ['name' => 'no_enchantments', 'level' => ''];
        }

        return $result;
    }
}
