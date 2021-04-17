<?php
namespace JavierLeon9966\Tracker;
use pocketmine\event\Listener;
use pocketmine\event\player\{PlayerInteractEvent, PlayerRespawnEvent, PlayerQuitEvent};
use pocketmine\item\{ItemIds, VanillaItems};
use pocketmine\network\mcpe\protocol\{ProtocolInfo, SetSpawnPositionPacket};
use pocketmine\network\mcpe\protocol\types\DimensionIds;
use pocketmine\player\Player;
use pocketmine\plugin\PluginBase;
use pocketmine\utils\TextFormat;
use JavierLeon9966\Tracker\command\{TrackCommand, UntrackCommand};
final class Tracker extends PluginBase implements Listener{
	private array $trackers = [];
	private static ?self $instance = null;
	public static function getInstance(): ?self{
		return self::$instance;
	}
	public function onEnable(): void{
		self::$instance = $this;
		$this->getServer()->getCommandMap()->registerAll('Tracker', [
			new TrackCommand($this),
			new UntrackCommand($this)
		]);
		$this->getServer()->getPluginManager()->registerEvents($this, $this);
	}
	public function updateCompass(Player $tracker): ?Player{
		$username = $tracker->getName();
		if(!isset($this->trackers[$username])){
			return null;
		}elseif(count($this->trackers[$username]) == 0){
			unset($this->trackers[$username]);
			$tracker->setSpawn($tracker->getSpawn());
			return null;
		}
		$currentDistanceSq = INF;
		$nearestPlayer = null;
		foreach($this->trackers[$username] as $player){
			if(!$player->isOnline()){
				unset($this->trackers[$username][$player->getName()]);
				continue;
			}
			$distanceSq = $player->getPosition()->distanceSquared($tracker->getPosition());
			if($distanceSq < $currentDistanceSq){
				$currentDistanceSq = $distanceSq;
				$nearestPlayer = $player;
			}
		}
		if($nearestPlayer === null){
			return null;
		}
		$pos = $nearestPlayer->getPosition()->floor();

		$tracker->getNetworkSession()->sendDataPacket(SetSpawnPositionPacket::worldSpawn($pos->x, $pos->y, $pos->z, DimensionIds::OVERWORLD));

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
	 * @ignoreCancelled true
	 * @priority MONITOR
	 */
	public function onPlayerInteract(PlayerInteractEvent $event): void{
		$tracker = $event->getPlayer();
		$username = $tracker->getName();
		$action = $event->getAction();
		if(isset($this->trackers[$tracker->getName()]) and $event->getItem()->getId() == ItemIds::COMPASS and ($action == PlayerInteractEvent::RIGHT_CLICK_AIR or $action == PlayerInteractEvent::RIGHT_CLICK_BLOCK)){
			$nearestPlayer = $this->updateCompass($tracker);
			if($nearestPlayer === null){
				return;
			}
			$tracker->sendMessage(TextFormat::GREEN."Compass is now pointing to {$nearestPlayer->getName()}.");
		}
	}

	/**
	 * @priority MONITOR
	 */
	public function onPlayerRespawn(PlayerRespawnEvent $event): void{
		$player = $event->getPlayer();
		if(isset($this->trackers[$player->getName()])){
			foreach($player->getInventory()->addItem(VanillaItems::COMPASS()) as $drop){
				$player->dropItem($drop);
			}
		}
	}

	/**
	 * @priority MONITOR
	 */
	public function onPlayerQuit(PlayerQuitEvent $event): void{
		unset($this->trackers[$event->getPlayer()->getName()]);
	}
}
