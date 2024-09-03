<?php
declare(strict_types=1);

namespace phpcube\listener;

use phpcube\CubeBlockCore;
use pocketmine\block\Block;
use pocketmine\event\block\BlockBreakEvent;
use pocketmine\event\block\BlockPlaceEvent;
use pocketmine\event\Listener;
use pocketmine\event\player\PlayerInteractEvent;
use pocketmine\item\ItemTypeIds;
use pocketmine\player\Player;

final class CubeBlockListener implements Listener
{
    /**
     * @param BlockBreakEvent $e
     * @return void
     */
    public function onBlockBreak(BlockBreakEvent $e): void
    {
        /** @Player $player */
        $player = $e->getPlayer();
        /** @Block $block */
        $block = $e->getBlock();
        if (!$e->isCancelled()) {
            CubeBlockCore::addLog($player, $block, "break");
        }
    }

    /**
     * @param BlockPlaceEvent $e
     * @return void
     */
    public function onBlockPlace(BlockPlaceEvent $e): void
    {
        /** @Player $player */
        $player = $e->getPlayer();
        /** @Transaction $tx */
        $tx = $e->getTransaction();
        foreach ($tx->getBlocks() as [$x, $y, $z, /** @Block $block */ $block]) {
            if (!($block instanceof Block)) continue;
            if (!$e->isCancelled()) {
                CubeBlockCore::addLog($player, $block, "place");
            }
        }
    }


    /*
     * Проверка логов
     */
    public function onInteract(PlayerInteractEvent $event): void
    {
        if ($event->getAction() !== PlayerInteractEvent::RIGHT_CLICK_BLOCK) return;
        $player = $event->getPlayer();
        $block = $event->getBlock();
        if ($player->getInventory()->getItemInHand()->getTypeId() !== ItemTypeIds::BOOK) return;
        if (!$player->hasPermission("coreitem.use"))  return;

        $playerName = strtolower($player->getName());
        $currTime = time();
        if (isset(CubeBlockCore::$cooldowns[$playerName]) && CubeBlockCore::$cooldowns[$playerName] > $currTime) return;
        CubeBlockCore::$cooldowns[$playerName] = $currTime + 1;

        $x = $block->getPosition()->getX();
        $y = $block->getPosition()->getY();
        $z = $block->getPosition()->getZ();
        $world = $block->getPosition()->getWorld()->getFolderName();
        $data = CubeBlockCore::getLogByXYZ($x, $y, $z, $world);
        if (!empty($data)) {
            CubeBlockCore::showLog($player, $data);
        } else {
            $player->sendMessage("§8(§aЛоги§8) §fЛоги блоков, §c§lНет взаимодействий§r с данным блоком!");
        }
    }
}