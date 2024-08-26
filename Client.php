<?php

include "Response.php";

class Client {

    public $id = null;
    public $name = "";
    public $connection;
    public $attempts = 0;
    public $correctGuesses = [];
    public $guesses = [];

    const SOCKET_ADDRESS = "localhost";
    const SOCKET_PORT = 25565;

    public function setup()
    {
        $this->connect();
        $this->handshake();
        while(true) {
            $this->handleIncomingMessages();
        }
    }

    private function connect()
    {
        $address = self::SOCKET_ADDRESS;
        $port = self::SOCKET_PORT;
        $this->connection = fsockopen($address, $port);
        if(!$this->connection) {
            echo "Could not connect to server";
        }
    }

    private function handshake()
    {
        // Not sure if this function is necessary, but it makes sure the client and socket both explicitly confirm that the id has been correctly received
        $handshakeComplete = false;
        while(!$handshakeComplete) {
            // Ask socket for an id
            $message = Response::formatResponse("", Response::MESSAGE_HANDSHAKE);
            $this->sendMessage($message);
            // Read the next response
            $response = $this->readResponse();
            if($response->type !== Response::MESSAGE_HANDSHAKE) {
                continue;
            }

            // Get id from response
            $id = $response->message;

            // Send the id back to the socket to confirm it is correct
            $message = Response::formatResponse($id, Response::MESSAGE_HANDSHAKE);
            $this->sendMessage($message);

            // Read the next response
            $response = $this->readResponse();
            if($response->type !== Response::MESSAGE_HANDSHAKE) {
                continue;
            }

            $handshakeComplete = $response->message === 'true';
        }

        $this->id = $id;
    }

    private function askInput($message)
    {
        $input = "";
        while(trim($input) === "") {
            $input = readline($message);
        }

        return $input;
    }

    private function handleIncomingMessages()
    {
        $message = $this->readResponse();
        $messageString = $message->message;
        $type = $message->type;
        switch($type)
        {
            case Response::MESSAGE_INPUT:
                $varName = $message->var;
                $input = $this->askInput($messageString);
                $input = Response::formatResponse("$varName:$input", Response::MESSAGE_SET_VAR);
                $this->sendMessage($input);
                break;
            case Response::MESSAGE_INFO:
                echo $messageString;
                break;
            case Response::MESSAGE_SET_VAR:
                $segments = Response::getVarFromString($messageString);
                $var = $segments[0];
                $value = $segments[1];
                $this->$var = $value;
                break;
            case Response::MESSAGE_PING:
                break;
            default:
                break;
        }
    }

    private function readResponse()
    {
        $message = fread($this->connection, 1024);
        if(!$message) {
            echo "Server has closed";
            exit;
        }
        return (object) json_decode($message, true);
    }

    private function sendMessage($message)
    {
        fwrite($this->connection, $message, strlen($message));
    }
}