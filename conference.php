<?php

/*
 * Example of Asterisk ARI Stasis Application using PHPARI library
 *
 * Scott Griepentrog <scott@griepentrog.com>
 *
 * Conference bridge implementation - use: stasis(conference,room)
 *
 */

	require_once "vendor/autoload.php";

	class ConferenceBridge
	{
		public function __construct($phpari)
		{
			$this->phpari = $phpari;
			$this->id = uniqid('conference-php-');
			$this->channels = array();
			$this->stasisEvent = new Evenement\EventEmitter();

			$result = $this->phpari->bridges->bridge_create('mixing', $this->id,"bogus");

			$this->stasisEvent->on("ChannelEnteredBridge", function ($event) {
				$this->channels = $event->bridge->channels;
				$this->phpari->bridges->playbackStart($this->id, "sound:confbridge-join");
				$this->phpari->stasisLogger->notice('Bridge now has: '.print_r($this->channels,true));
			});
			$this->stasisEvent->on("ChannelLeftBridge", function ($event) {
				$this->channels = $event->bridge->channels;
				$this->phpari->bridges->playbackStart($this->id, "sound:confbridge-leave");
				$this->phpari->stasisLogger->notice('Bridge now has: '.print_r($this->channels,true));
			});
		}

		// add a channel to the bridge
		public function addChannel($channel)
		{
			echo 'Adding channel '.$channel->id."\n";
			$this->phpari->channels->channel_answer($channel->id);

			if (!count($this->channels)) {
				$this->phpari->channels->channel_playback($channel->id, "sound:conf-onlyperson");
			}
			$result = $this->phpari->bridges->bridge_addchannel($this->id, $channel->id);
		}
	}

	class ConferenceChannel
	{
		public function __construct($phpari, $event)
		{
			$this->phpari = $phpari;
			$this->phpari->stasisLogger->notice('Channel Created: '.print_r($event,true));
			$this->stasisEvent = new Evenement\EventEmitter();

			$this->channel = $event->channel;

			$this->phpari->default_bridge->addChannel($event->channel);

			$this->dtmf = '';

			$this->stasisEvent->on("ChannelStateChange", function ($event) {
				$this->channel = $event->channel;
				$this->phpari->stasisLogger->notice($this->channel->name.' State Change: '.$this->channel->state);
			});
			$this->stasisEvent->on("StasisEnd", function ($event) {
				unset ($this->phpari->channel[$this->channel->id]);
				unset ($this);
			});
			$this->stasisEvent->on("ChannelDtmfReceived", function ($event) {
				$this->dtmf .= $event->digit;
				$this->phpari->stasisLogger->notice($this->channel->name.' DTMF: '.$this->dtmf);
			});
		}
	}

	class ConferenceStasisApp extends phpari
	{
		public function __construct()
		{
			$appName="conference";

			// initialize the underlying phpari class
			parent::__construct($appName);

			// initialize the handler for websocket messages
			$this->WebsocketClientHandler();

			// access to the needed api's
			$this->channels = new channels($this);
			$this->bridges = new bridges($this);

			// conference bridges
			$this->bridge = array();

			// conference channels
			$this->channel = array();

			// default bridge
			$this->default_bridge = new ConferenceBridge($this);
			$this->bridge[$this->default_bridge->id]=$this->default_bridge;
		}

		// handle the websocket connection, passing stasis events to handler above
		public function WebsocketClientHandler()
		{
			$this->stasisClient->on("request", function ($headers) {
				$this->stasisLogger->notice("Request received!");
			});

			$this->stasisClient->on("handshake", function () {
				$this->stasisLogger->notice("Handshake received!");
			});

			$this->stasisClient->on("message", function ($message) {
				$event=json_decode($message->getData());
				$this->stasisLogger->notice('Received event: '.$event->type);
				if (!empty($event->bridge->id)) {
					if (empty($this->bridge[$event->bridge->id])) {
						$this->stasisLogger->notice('Bridge event received for unknown bridge');
						return;
					}
					$bridge = $this->bridge[$event->bridge->id];
					$this->stasisLogger->notice('Passing event to bridge '.$bridge->id);
					$bridge->stasisEvent->emit($event->type, array($event));
				} else if (!empty($event->channel->id)) {
					if (empty($this->channel[$event->channel->id])) {
						$this->channel[$event->channel->id] = new ConferenceChannel($this, $event);
					}
					$this->stasisLogger->notice('Passing event to channel '.$event->channel->id);
					$channel = $this->channel[$event->channel->id];
					$channel->stasisEvent->emit($event->type, array($event));
				} else {
					if (empty($event->playback))
						$this->stasisLogger->notice('Unhandled event! '.print_r($event,true));
				}
			});
		}

		// initiate the websocket connection and run the event loop
		public function run()
		{
			$this->stasisClient->open();
			$this->stasisLoop->run();
			// run() does not return
		}

	}

	$app = new ConferenceStasisApp();
	$app->run();
