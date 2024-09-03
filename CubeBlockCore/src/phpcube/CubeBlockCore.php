<?php
declare(strict_types=1);

namespace phpcube;

use pocketmine\plugin\PluginBase;
use pocketmine\utils\SingletonTrait;
use pocketmine\Server;
use pocketmine\block\Block;
use pocketmine\player\Player;

use SQLite3;

use phpcube\form\SimpleForm;
use phpcube\listener\CubeBlockListener;
class CubeBlockCore extends PluginBase{

    use SingletonTrait;

    public static array $cooldowns = [];

    /**
     * @var SQLite3|null
     */
    public static SQLite3|null $logDb = null;
    /**
     * @var string
     */
    public string $LogSql = /*** @lang sql * */
        'CREATE TABLE IF NOT EXISTS `corelog` (
		`account`	TEXT NOT NULL,
		`x`			TEXT NOT NULL,
		`y`			TEXT NOT NULL,
		`z`			TEXT NOT NULL,
		`world`		TEXT NOT NULL,
		`action`	TEXT NOT NULL,
		`block`		TEXT NOT NULL,
		`time`		TEXT NOT NULL
	);';

    public function onEnable(): void
    {
        self::setInstance($this);

        if (!is_dir($this->getDataFolder())) mkdir($this->getDataFolder());

        self::$logDb = new SQLite3($this->getDataFolder() . "logDb.db");
        self::$logDb->exec($this->LogSql);

        Server::getInstance()->getPluginManager()->registerEvents(new CubeBlockListener(), $this);
        Server::getInstance()->getLogger()->info('§8(§aЛоги§8) §fПлагин был включен');
    }

    /**
     * @param Player $player
     * @param array $datax
     * @param int $page
     * @param string $filter
     * @return void
     */
    public static function showLog(Player $player, array $datax, int $page = 1, string $filter = "all"): void
    {
        $form = new SimpleForm(function (Player $player, $data) use ($datax, $page, $filter) {
            if ($data === "exit") {
                return;
            }
            if ($data === "prev") {
                self::showLog($player, $datax, $page - 1, $filter);
            }
            if ($data === "next") {
                self::showLog($player, $datax, $page + 1, $filter);
            }
            if ($data === "filter_place") {
                self::showLog($player, $datax, 1, "place");
            }
            if ($data === "filter_break") {
                self::showLog($player, $datax, 1, "break");
            }
            if ($data === "filter_all") {
                self::showLog($player, $datax, 1, "all");
            }
        });

        $action = "";
        if ($filter === "all") {
            $log = "";
            $paginatedData = self::paginateData($datax, max($page, 1));
            $totalPages = self::getTotalPages($datax) ?? 1;
            foreach ($paginatedData as $index => $values) {
                if ($values['action'] == "place") {
                    $action = "§aПоставил";
                }
                if ($values['action'] == "break") {
                    $action = "§cСломал";
                }
                $log .= "\n§r§c§l#{$values['rowid']}§r§f §b[" .  $values['time']  . "]§f Игрок: §a[" . $values['account'] . "]§f " . $action . "§f блок: " .
                    "§e" . $values['block'] . ' §fна локации: §a' . "X: {$values['x']}, Y: {$values['y']}, Z: {$values['z']}, мир: {$values['world']}";
            }
            $form->addButton("§0§lВыход\n§8[§0§l Выход из меню §8]", -1, "", "exit");
            $form->addButton("§8§lНазад\n§8[§0§l Перейти на предыдущую страницу §8]", -1, "", "prev");
            $form->addButton("§8§lВперед\n§8[§0§l Перейти на следующую страницу §8]", -1, "", "next");
            $form->addButton("§a§lПоставленные\n§8[§0§l Показать только поставленные блоки §8]", -1, "", "filter_place");
            $form->addButton("§c§lСломанные\n§8[§0§l Показать только сломанные блоки §8]", -1, "", "filter_break");
            $form->setTitle("§s§r§8(§aЛоги§8) §fЛоги блоков");
            $form->setContent("Список действий с данным блоком. Страница §a{$page} §fиз §a" . $totalPages . "§f\n" . $log);
        } else {
            $log = "";
            $filteredData = self::filterData($datax, $filter) ?? [];
            $paginatedData = self::paginateData($filteredData,  max($page, 1));
            $totalPages = self::getTotalPages($filteredData) ?? 1;
            foreach ($paginatedData as $index => $values) {
                if ($values['action'] === "place") {
                    $action = "§aПоставил";
                }
                if ($values['action'] === "break") {
                    $action = "§cСломал";
                }
                $log .= "\n§r§r§c§l#{$values['rowid']}§r§f §b[" . $values['time'] . "]§f Игрок: §a[" . $values['account'] . "]§f " . $action. "§f блок: " .
                    "§e" . $values['block'] . ' §fна локации: §a' . "X: {$values['x']}, Y: {$values['y']}, Z: {$values['z']}, мир: {$values['world']}";
            }
            $form->addButton("§0§lВыход\n§8[§0§l Выход из меню §8]", -1, "", "exit");
            $form->addButton("§8§lНазад\n§8[§0§l Перейти на предыдущую страницу §8]", -1, "", "prev");
            $form->addButton("§8§lВперед\n§8[§0§l Перейти на следующую страницу §8]", -1, "", "next");
            $form->addButton("§f§lВсе\n§8[§0§l Показать все блоки §8]", -1, "", "filter_all");
            $form->addButton("§a§lПоставленные\n§8[§0§l Показать только поставленные блоки §8]", -1, "", "filter_place");
            $form->addButton("§c§lСломанные\n§8[§0§l Показать только сломанные блоки §8]", -1, "", "filter_break");
            $form->setTitle("§s§r§8(§aЛоги§8) §fЛоги блоков");
            $form->setContent("Список действий с данным блоком. Страница§a {$page} §fиз §a" . $totalPages . "§r\n" . $log);
        }
        $player->sendForm($form);
    }

    /**
     * @param array $data
     * @param string $filter
     * @return array
     */
    public static function filterData(array $data, string $filter): array
    {
        if ($filter === "all") {
            return $data;
        }
        $filteredData = [];
        foreach ($data as $values) {
            if (isset($values['action']) && $values['action'] === $filter) {
                $filteredData[] = $values;
            }
        }
        return $filteredData;
    }

    /**
     * @param array $data
     * @param int $page
     * @return array
     */
    public static function paginateData(array $data, int $page): array
    {
        $perPage = 10;
        $start = ($page - 1) * $perPage;
        return array_slice($data, $start, min($perPage, count($data) - $start));
    }

    /**
     * @param array $data
     * @return int|float
     */
    public static function getTotalPages(array $data): int|float
    {
        $perPage = 10;
        return ceil(count($data) / max($perPage, 1));
    }

    /**
     * @param Player $player
     * @param Block $block
     * @param string $type
     * @return bool
     */
    public static function addLog(Player $player, Block $block, string $type): bool
    {
        $statement = self::$logDb->prepare(/*** @lang sql ***/"SELECT * FROM `corelog` WHERE `account` = :account AND `x` = :x AND `y` = :y AND `z` = :z AND `world` = :world AND `action` = :action AND `block` = :block");
        $statement->bindValue(":account", strtolower($player->getName()));
        $statement->bindValue(":x", $block->getPosition()->getX());
        $statement->bindValue(":y", $block->getPosition()->getY());
        $statement->bindValue(":z", $block->getPosition()->getZ());
        $statement->bindValue(":world", $player->getLocation()->getWorld()->getFolderName());
        $statement->bindValue(":action", $type);
        $statement->bindValue(":block", $block->getName() . " | id: " . $block->getTypeId());
        $result = $statement->execute();

        if ($result->fetchArray()) {
            return false;
        }

        $stmt = self::$logDb->prepare(/*** @lang sql ***/"INSERT INTO `corelog` (`account`, `x`, `y`, `z`, `world`, `action`, `block`, `time`)  VALUES  (:account, :x, :y, :z, :world, :action, :block, :time)");
        $stmt->bindValue(":account", strtolower($player->getName()));
        $stmt->bindValue(":x", $block->getPosition()->getX());
        $stmt->bindValue(":y", $block->getPosition()->getY());
        $stmt->bindValue(":z", $block->getPosition()->getZ());
        $stmt->bindValue(":world", $player->getLocation()->getWorld()->getFolderName());
        $stmt->bindValue(":action", $type);
        $stmt->bindValue(":block", $block->getName() . " | id: " . $block->getTypeId());
        date_default_timezone_set('Europe/Moscow');
        $stmt->bindValue(":time", date("d.M.y - H:i:s"));
        $stmt->execute();
        return self::$logDb->changes() == 1;
    }

    /**
     * @param $x
     * @param $y
     * @param $z
     * @param $world
     * @return array|bool
     */
    public static function getLogByXYZ($x, $y, $z, $world): bool|array
    {
        $statement = self::$logDb->prepare(/*** @lang sql ***/"SELECT rowid, * FROM `corelog` WHERE `x` = :x AND `y` = :y AND `z` = :z AND `world` = :world");
        $statement->bindValue(":x", $x);
        $statement->bindValue(":y", $y);
        $statement->bindValue(":z", $z);
        $statement->bindValue(":world", $world);
        $result = $statement->execute();
        $data = [];
        while ($row = $result->fetchArray(SQLITE3_ASSOC | SQLITE3_NUM)) {
            $data[] = $row;
        }
        return $data;
    }
}