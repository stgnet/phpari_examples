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
			$this->members = array();

			$result = $this->phpari->bridges->bridge_create('mixing', $this->id,"bogus");
			print_r($result);
		}

		// add a channel to the bridge
		public function addChannel($channel)
		{
			echo 'Adding channel '.$channel->id."\n";
			$this->phpari->channels->channel_answer($channel->id);

			if (!count($this->members)) {
				$this->phpari->channels->channel_playback($channel->id, "sound:conf-onlyperson");
			} else {
				$this->phpari->channels->channel_playback($channel->id, "sound:conf-thereare");
			}

			$this->members[$channel->id]=$channel;

			$result = $this->phpari->bridges->bridge_addchannel($this->id, $channel->id);
			print_r($result);
		}

		// the channel has already left, remove it from members
		public function leftChannel($channel)
		{
		}
	}

    class ConferenceStasisApp extends phpari
    {
        public function __construct()
        {
            $conf=parse_ini_file("/etc/ari.ini");

            $appName="conference";

            // initialize the ARI connection
            parent::__construct($conf['USERNAME'], $conf['PASSWORD'], $appName,
                                $conf['SERVER'], $conf['PORT'], $conf['ENDPOINT']);

            // create a separate event handler for Stasis events
            $this->stasisEvent = new Evenement\EventEmitter();
            $this->StasisAppEventHandler();

            // initialize the handler for websocket messages
            $this->WebsocketClientHandler();

            // access to the needed api's
            $this->channels = new channels($this);
			$this->bridges = new bridges($this);

			// conference bridges
			$this->bridge = array();
        }

		public function findBridge($channel_id)
		{
			foreach ($this->bridge as $number => $bridge) {
				if (!empty($this->bridge->members[$channel_id])) {
					return $bridge;
				}
			}
			return NULL;
		}

        // process stasis events
        public function StasisAppEventHandler()
        {
            $this->stasisEvent->on('StasisStart', function ($event) {
				$number = $event->args[0];
				if (empty($this->bridge[$number])) {
					$this->bridge[$number] = new ConferenceBridge($this);
				}
				$this->bridge[$number]->addChannel($event->channel);
            });

            $this->stasisEvent->on('PlaybackFinished', function ($event) {
                $channel_id=str_replace('channel:', '', $event->playback->target_uri);
                //$this->channels->channel_delete($channel_id);
            });
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
                $this->stasisEvent->emit($event->type, array($event));
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

    $monkeys = new ConferenceStasisApp();
    $monkeys->run();
