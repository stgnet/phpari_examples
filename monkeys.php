<?php

/*
 * Example of Asterisk ARI Stasis Application using PHPARI library
 *
 * Scott Griepentrog <scott@griepentrog.com>
 *
 * Creates stasis app that answers channel, plays monkeys, and hangs up.
 *
 * To test, originate a call using CLI:
 *
 * CLI> originate PJSIP/(extension) application stasis monkeys
 *
 * or via the dialplan:
 *
 * exten => 500,1,Stasis(monkeys)
 *
 * For more details on configuring Asterisk and ARI:
 * https://wiki.asterisk.org/wiki/display/AST/Getting+Started+with+ARI
 *
 */

    require_once "vendor/autoload.php";

    class MonkeysStasisApp extends phpari
    {
        public function __construct()
        {
            /*
             * load ARI configuration paramters from an external file, example:
             *
             * USERNAME=ArthurDent
             * PASSWORD=42
             * SERVER=zaphod.example.org
             * PORT=8088
             * ENDPOINT=/ari
            */
            $conf=parse_ini_file("/etc/ari.ini");

            $appName="monkeys";

            // initialize the ARI connection
            parent::__construct($conf['USERNAME'], $conf['PASSWORD'], $appName,
                                $conf['SERVER'], $conf['PORT'], $conf['ENDPOINT']);

            // create a separate event handler for Stasis events
            $this->stasisEvent = new Evenement\EventEmitter();
            $this->StasisAppEventHandler();

            // initialize the handler for websocket messages
            $this->WebsocketClientHandler();

            // access to the channels api
            $this->channels = new channels($this);
        }

        // process stasis events
        public function StasisAppEventHandler()
        {
            $this->stasisEvent->on('StasisStart', function ($event) {
                $this->stasisLogger->notice('Starting monkeys on '.$event->channel->name);
                // first answer the channel (otherwise playback is early media)
                $this->channels->channel_answer($event->channel->id);
                // play the desired sound on the channel
                $this->channels->channel_playback($event->channel->id, "sound:tt-monkeys");
            });

            $this->stasisEvent->on('PlaybackFinished', function ($event) {
                $channel_id=str_replace('channel:', '', $event->playback->target_uri);
                $this->stasisLogger->notice('Hanging up channel');
                $this->channels->channel_delete($channel_id);
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

    $monkeys = new MonkeysStasisApp();
    $monkeys->run();
