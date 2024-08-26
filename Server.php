<?php

include "Client.php";
include "Game.php";

$server = new Server();

class Server {

    public $clients = [];

    public $game = null;

    function __construct() {
        $socket = socket_create(AF_INET6, SOCK_STREAM, 0);

        // TODO Go through each function and if it can return false or throw an error, print out the error

        $this->setup($socket);

        while(true) {
            $connection = socket_accept($socket);
            $this->setupConnectedClient($connection);
        }
    }

    function setup($socket)
    {
        $address = Client::SOCKET_ADDRESS;
        $port = Client::SOCKET_PORT;

        // Bind the socket to a port on the address provided
        socket_bind($socket, $address, $port);
        // Listens for connections to this socket
        socket_listen($socket);
        echo "Listening on $address on port $port\n";

        $this->game = new Game($this);
    }

    function setupConnectedClient($connection)
    {
        $id = uniqid("player_", true);
        echo "Client $id has connected to the server\n";
        echo "Initiating handshake for client $id\n";
        $this->handshake($connection, $id);
        echo "Handshake completed for client $id\n\n";

        $name = $this->askForInput($connection, "\nWhat's your name? ", "name");

        $client = new Client();
        $client->id = $id;
        $client->connection = $connection;
        $client->name = $name;
        $this->clients[] = $client;

        $this->broadcast("\n\e[96m$client->name has joined the game\e[39m\n\n", Response::MESSAGE_INFO);

        $this->game->checkCanStart();
    }

    function handshake($client, $id)
    {
        $handshakeComplete = false;
        while(!$handshakeComplete) {

            // Wait for client to ask for id
            $response = $this->readResponse($client);

            if($response->type !== Response::MESSAGE_HANDSHAKE) {
                continue;
            }

            // Generate id and send to client
            $this->sendMessage($client, $id, Response::MESSAGE_HANDSHAKE);

            // Wait for client to send id
            $response = $this->readResponse($client);
            $handshakeComplete = $id === $response->message;
            $message = $handshakeComplete ? 'true': 'false';

            // If id correct, send message 'true' else send message 'false'
            $this->sendMessage($client, $message, Response::MESSAGE_HANDSHAKE);
        }
    }

    public function askForInput($client, $message, $var = null)
    {
        $this->sendMessage($client, $message, Response::MESSAGE_INPUT, $var);

        $value = null;
        while(!$value) {
            $response = $this->readResponse($client);

            if($response->type !== Response::MESSAGE_SET_VAR) {
                continue;
            }

            $value = Response::getVarFromString($response->message)[1];
        }

        return $value;
    }

    function sendMessage($client, $message, $type, $var = null) {
        $message = Response::formatResponse($message, $type, $var);
        // TODO Remove below - WIP causes errors
        sleep(1.5);
        $success = socket_write($client, $message, strlen($message));
        if(!$success) {
            $this->removeClient($client);
        }
        return $success;
    }

    function readResponse($client)
    {
        $message = socket_read($client, 1024);
        if(!$message) {
            $this->removeClient($client);
        }
        return (object) json_decode($message, true);
    }

    public function broadcast($message, $type, $excludeServer = false)
    {
        if(!$excludeServer) {
            echo $message;
        }

        foreach ($this->clients as $client) {
            $this->sendMessage($client->connection, $message, $type);
        }
    }

    public function removeClient($client) {
        $clientName = $client->name;
        unset($this->clients[$client]);
        $message = "\e[96m$clientName has quit the game\e[39m\n";
        echo $message;
        $this->broadcast($message, Response::MESSAGE_INFO);
    }
}