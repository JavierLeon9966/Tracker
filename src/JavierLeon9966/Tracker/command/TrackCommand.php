<?php
namespace JavierLeon9966\Tracker\command;
use pocketmine\command\{Command, CommandSender};
use pocketmine\command\utils\InvalidCommandSyntaxException;
use pocketmine\item\VanillaItems;
use pocketmine\player\Player;
use pocketmine\plugin\{PluginOwned, PluginOwnedTrait};
use pocketmine\utils\TextFormat;
use JavierLeon9966\Tracker\Tracker;
class TrackCommand extends Command implements PluginOwned{
	use PluginOwnedTrait;
	public function __construct(Tracker $plugin){
		$this->plugin = $plugin;
		parent::__construct(
			'track',
			'Track a player\'s location',
			'/track <name: player>'
		);
		$this->setPermission('tracker.command.track');
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
			$sender->sendMessage('You can not track yourself');
			return true;
		}elseif($this->getPlugin()->isTracking($sender->getName(), $player)){
			$sender->sendMessage("You are already tracking {$player->getName()}");
			return true;
		}
		$this->getPlugin()->addTracker($sender->getName(), $player);
		foreach($sender->getInventory()->addItem(VanillaItems::COMPASS()) as $drop){
			$sender->dropItem($drop);
		}
		$this->getPlugin()->updateCompass($sender);
		$sender->sendMessage(TextFormat::GREEN."Compass now will point to {$player->getName()}.");
		return true;
	}
}