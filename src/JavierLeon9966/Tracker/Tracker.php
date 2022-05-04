<?php

declare(strict_types = 1);

namespace JavierLeon9966\Tracker;

use JavierLeon9966\Tracker\command\{TrackCommand, UntrackCommand};

use pocketmine\event\Listener;
use pocketmine\event\player\{PlayerItemUseEvent, PlayerRespawnEvent, PlayerQuitEvent};
use pocketmine\item\{ItemIds, VanillaItems};
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\Server;
use pocketmine\utils\{SingletonTrait, TextFormat};

final class Tracker extends PluginBase implements Listener{
	use SingletonTrait;

	/**
	 * @var Player[][]
	 * @phpstan-var array<string, array<string, Player>>
	 */
	private array $trackers = [];

	private static function make(): self{
		$plugin = Server::getInstance()->getPluginManager()->getPlugin('Tracker');
		return $plugin instanceof self ? $plugin :
			throw new \BadMethodCallException('Cannot get instance of a disabled plugin');
	}

	public function onEnable(): void{
		self::$instance = $this;
		$server = $this->getServer();
		$server->getCommandMap()->registerAll('Tracker', [
			new TrackCommand($this),
			new UntrackCommand($this)
		]);
		$server->getPluginManager()->registerEvents($this, $this);
	}

	protected function onDisable(): void{
		self::$instance = null;
	}

	public function updateCompass(Player $tracker): ?Player{
		$username = $tracker->getName();
		$trackerPos = $tracker->getPosition();
		if(!isset($this->trackers[$username])){
			return null;
		}elseif(count($this->trackers[$username]) === 0){
			unset($this->trackers[$username]);
			$tracker->getNetworkSession()->syncWorldSpawnPoint($tracker->getWorld()->getSpawnLocation());
			return null;
		}

		$currentDistanceSq = INF;
		$nearestPlayer = null;
		foreach($this->trackers[$username] as $player){
			if(!$player->isOnline()){
				unset($this->trackers[$username][$player->getName()]);
				continue;
			}
			$distanceSq = $player->getPosition()->distanceSquared($trackerPos);
			if($distanceSq < $currentDistanceSq){
				$currentDistanceSq = $distanceSq;
				$nearestPlayer = $player;
			}
		}
		if($nearestPlayer === null){
			return null;
		}

		$tracker->getNetworkSession()->syncWorldSpawnPoint($nearestPlayer->getPosition()); // not supported but is the only way

		return $nearestPlayer;
	}

	public function addTracker(string $tracker, Player $player): void{
		$this->trackers[$tracker][$player->getName()] = $player;
	}

	public function removeTracker(string $tracker, Player $player): void{
		unset($this->trackers[$tracker][$player->getName()]);
	}

	public function isTracking(string $tracker, Player $player): bool{
		return isset($this->trackers[$tracker][$player->getName()]);
	}

	/**
	 * @priority MONITOR
	 */
	public function onPlayerItemUse(PlayerItemUseEvent $event): void{
		$tracker = $event->getPlayer();
		$username = $tracker->getName();
		if(!isset($this->trackers[$tracker->getName()]) || $event->getItem()->getId() !== ItemIds::COMPASS){
			return;
		}
		$nearestPlayer = $this->updateCompass($tracker);
		if($nearestPlayer !== null){
			$tracker->sendMessage(TextFormat::GREEN."Compass is now pointing to {$nearestPlayer->getName()}.");
		}
	}

	/**
	 * @priority MONITOR
	 */
	public function onPlayerRespawn(PlayerRespawnEvent $event): void{
		$player = $event->getPlayer();
		if(!isset($this->trackers[$player->getName()])){
			return;
		}
		foreach($player->getInventory()->addItem(VanillaItems::COMPASS()) as $drop){
			$player->dropItem($drop);
		}
	}

	/**
	 * @priority MONITOR
	 */
	public function onPlayerQuit(PlayerQuitEvent $event): void{
		unset($this->trackers[$event->getPlayer()->getName()]);
	}
}
