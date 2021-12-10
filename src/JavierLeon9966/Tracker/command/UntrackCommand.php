<?php

declare(strict_types = 1);

namespace JavierLeon9966\Tracker\command;

use JavierLeon9966\Tracker\Tracker;

use pocketmine\command\{Command, CommandSender};
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\player\Player;
use pocketmine\plugin\{PluginOwned, PluginOwnedTrait};
use pocketmine\utils\TextFormat;

class UntrackCommand extends Command implements PluginOwned{
	use PluginOwnedTrait;

	public function __construct(Tracker $plugin){
		parent::__construct(
			'untrack',
			'Untrack a player\'s location',
			'/untrack <name: player>'
		);
		$this->setPermission('tracker.command.untrack');
		$this->owningPlugin = $plugin;
	} 
	public function execute(CommandSender $sender, string $commandLabel, array $args){
		if(!$this->testPermission($sender)){
			return true;
		}
		if(count($args) == 0){
			throw new InvalidCommandSyntaxException;
		}elseif(!$sender instanceof Player){
			$sender->sendMessage('This command must be executed as a player');
			return false;
		}
		$player = $sender->getServer()->getPlayer($args[0]);
		if($player === null){
			$sender->sendTranslation(TextFormat::RED.'%commands.generic.player.notFound');
			return true;
		}elseif($player === $sender){
			$sender->sendMessage('You can not untrack yourself');
			return true;
		}
		/** @var Tracker $plugin */
		$plugin = $this->owningPlugin;
		if(!$plugin->isTracking($sender->getName(), $player)){
			$sender->sendMessage("You are already not tracking {$player->getName()}");
			return true;
		}
		$plugin->removeTracker($sender->getName(), $player);
		$plugin->updateCompass($sender);
		$sender->sendMessage(TextFormat::GREEN."Compass is no longer pointing to {$player->getName()}.");
		return true;
	}
}