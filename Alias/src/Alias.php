<?php

namespace Alias;

use pocketmine\command\Command;
use pocketmine\command\CommandSender;
use pocketmine\event\Listener;
use pocketmine\plugin\PluginBase;
use pocketmine\event\player\PlayerJoinEvent;
use pocketmine\player\Player;
use pocketmine\utils\Config;
use economyCore\economyCore;

class Alias extends PluginBase implements Listener
{
    private static $instance = null;

    public static function getInstance()
    {
        return self::$instance;
    }

    public array $taskList;

    public function onLoad(): void
    {
        self::$instance = $this;
        $this->getLogger()->info("Alias 加载中！");
    }

    public function onEnable(): void
    {
        // 创建目录
        @mkdir($this->getDataFolder(), 0777, true);

        // 创建配置文件
        $this->config = new Config(
            $this->getDataFolder() . "config.yml",
            Config::YAML,
            array(
                "needCurrency" => false,
                "CurrencyId" => 0,
                "CurrencyAmount" => 1000
            )
        );
        $this->config->save();

        // 创建数据文件
        $this->data = new Config(
            $this->getDataFolder() . "data.json",
            Config::JSON,
            array(
                "playerData" => []
            )
        );
        $this->data->save();

        $this->getServer()->getPluginManager()->registerEvents($this, $this);
        $this->getLogger()->info("Alias 已启用！");
    }

    public function onDisable(): void
    {
        $this->getLogger()->info("Alias 已关闭！");
    }

    public function isNewPlayer(string $name)
    {
        if ($this->data->get("playerData") == []) {
            return true;
        }

        foreach ($this->data->get("playerData") as $playerData) {
            if ($playerData['name'] == $name) {
                return false;
            }
        }

        return true;
    }

    public function onPlayerJoin(PlayerJoinEvent $event)
    {
        $name = $event->getPlayer()->getName();

        // 如果是新玩家
        if ($this->isNewPlayer($event->getPlayer()->getName())) {
            // 创建玩家数据
            $playersData = $this->data->get("playerData");
            array_push(
                $playersData,
                [
                    "name" => $name,
                    "Alias" => $name
                ]
            );
            $this->data->set("playerData", $playersData);
            $this->data->save();
        }
    }

    // 定义别名
    public function rename($player, $Alias)
    {
        $name = $player->getName();

        $playersData = $this->data->get("playerData");
        // 查重
        foreach ($playersData as $value) {
            if ($value['Alias'] == $Alias) {
                return 'repeat';
            }
        }

        // 检查货币
        if ($this->config->get("needCurrency")) {
            $CurrencyId = $this->config->get("CurrencyId");
            $CurrencyAmount = $this->config->get("CurrencyAmount");

            $temp = economyCore::getInstance()->subPlayerCurrency($name, $CurrencyId, $CurrencyAmount);

            if ($temp != 'OK') {
                return $temp;
            }
        }

        // 修改
        foreach ($playersData as &$value) {
            if ($value['name'] != $name) {
                continue;
            }
            $value['Alias'] = $Alias;
        }
        $this->data->set("playerData", $playersData);
        $this->data->save();

        $player->setDisplayName($Alias);

        return 'OK';
    }

    // 获取别名
    public function getAlias($name)
    {
        $playersData = $this->data->get("playerData");
        foreach ($playersData as $value) {
            if ($value['name'] != $name) {
                continue;
            }
            return $value['Alias'];
        }
    }

    // 获取真名
    public function getTrueName($Alias)
    {
        $playersData = $this->data->get("playerData");
        foreach ($playersData as $value) {
            if ($value['Alias'] != $Alias) {
                continue;
            }
            return $value['name'];
        }
    }

    public function onCommand(CommandSender $sender, Command $command, string $label, array $args): bool
    {
        $CurrencyName = economyCore::getInstance()->readCurrencyName($this->config->get("CurrencyId"));
        $CurrencyAmount = $this->config->get("CurrencyAmount");
        $name = $sender->getName();

        switch ($command->getName()) {
            case "alias":
                if (count($args) == 0) {
                    $sender->sendMessage("§a[alias] 非法的指令！");
                    return true;
                }
                switch ($args[0]) {
                    case "rename":
                        if (count($args) == 1) {
                            $sender->sendMessage("§a[alias] 非法的指令！");
                            return true;
                        }
                        $this->taskList[$name] = $args[1];
                        if ($this->config->get("needCurrency")) {
                            $sender->sendMessage("§a[alias] 您将花费" . $CurrencyAmount . $CurrencyName . "改名为" . $args[1] . ",您确定吗？");
                        } else {
                            $sender->sendMessage("§a[alias] 您将改名为" . $args[1] . ",您确定吗？");
                        }
                        $sender->sendMessage("§a[alias] /alias y    确认");
                        $sender->sendMessage("§a[alias] /alias n    拒绝");
                        return true;
                    case "help":
                        $sender->sendMessage("§a[alias] alias使用指南");
                        $sender->sendMessage("§a[alias] 指令：alias rename 新名字");
                        $sender->sendMessage("§a[alias] 解释：更改昵称");
                        $sender->sendMessage("§a[alias] 指令：alias y");
                        $sender->sendMessage("§a[alias] 解释：确认");
                        $sender->sendMessage("§a[alias] 指令：alias n");
                        $sender->sendMessage("§a[alias] 解释：拒绝");
                        return true;
                    case "y":
                        if ($this->taskList[$name] == "") {
                            $sender->sendMessage("§a[alias] 找不到更名任务！");
                            return true;
                        }
                        switch ($this->rename($sender, $this->taskList[$name])) {
                            case "repeat":
                                $sender->sendMessage("§a[alias] 此别名已被占用！");
                                return true;
                            case "OK":
                                $sender->sendMessage("§a[alias] 更改别名成功！");
                                return true;
                            case "noMoney":
                                $sender->sendMessage("§a[alias] 您没有足够多的" . $CurrencyName . "用于改名！");
                                return true;
                            default:
                                $sender->sendMessage("§a[alias] 发生了未知错误！");
                                return true;
                        }
                    case "n":
                        $this->taskList[$name] = "";
                        $sender->sendMessage("§a[alias] 更名任务已取消！");
                        return true;
                    default:
                        $sender->sendMessage("§a[alias] 请输入正确的指令！");
                        return true;

                }
        }
    }
}