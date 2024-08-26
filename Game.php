<?php

class Game {

    const GAME_STATE_IN_PROGRESS = 0;
    const GAME_STATE_ENDED = 1;

    const TOTAL_ATTEMPTS = 5;

    private $server;
    private $gameState;
    public $word = "";

    function __construct($server)
    {
        $this->server = $server;
    }

    public function checkCanStart()
    {
        if(count($this->server->clients) < 2) {
            $this->server->broadcast("Waiting for more players...\n", Response::MESSAGE_INFO);
            return;
        }

        if($this->isGameInProgress()) {
            return;
        }

        $this->restartGame();
    }

    function restartGame()
    {
        $this->gameState = self::GAME_STATE_IN_PROGRESS;
        $this->server->broadcast("\nGame starting...\n\n", Response::MESSAGE_INFO);
        $this->resetClientAttempts();
        $this->resetClientGuesses();
        $this->word = $this->randomWord();
        $length = strlen($this->word);
        $this->resetCorrectGuesses();
        echo "The word is \"$this->word\"\n\n";
        $this->server->broadcast("The word is $length characters long\n", Response::MESSAGE_INFO, true);
        $this->handleGuesses();
    }

    function randomWord()
    {
        $words = file("words.csv");
        $max = count($words) -1;
        $index = random_int(0, $max);
        return trim($words[$index]);
    }

    public function isGameInProgress()
    {
        return $this->gameState === self::GAME_STATE_IN_PROGRESS;
    }

    function handleGuesses()
    {
        while($this->isGameInProgress()) {
            foreach($this->server->clients as $client) {
                if($client->attempts === self::TOTAL_ATTEMPTS) {
                    continue;
                }

                $this->server->sendMessage($client->connection, $this->formatGuesses($client), Response::MESSAGE_INFO);
                $this->server->sendMessage($client->connection, $this->formatAttemptString($client), Response::MESSAGE_INFO);
                $this->server->sendMessage($client->connection, $this->formatCorrectGuesses($client), Response::MESSAGE_INFO);

                $guess = $this->server->askForInput($client->connection, "Your guess is: ", "guess");
                $client->guesses[] = $guess;
                if(strlen($guess) > 1) {
                    if($guess !== $this->word) {
                        $this->handleIncorrectGuess($client);
                        continue;
                    }

                    if($guess === $this->word) {
                        $client->correctGuesses = str_split($guess);
                        $this->gameState = self::GAME_STATE_ENDED;
                        $this->server->broadcast("\n\n\e[92m$client->name has won the game\e[39m\n\n", Response::MESSAGE_INFO);
                        $this->server->broadcast("\e[92mThe word was $this->word\e[39m\n\n", Response::MESSAGE_INFO, false);
                        break;
                    }
                }

                $correctGuess = false;
                $segments = str_split($this->word);
                foreach($segments as $index => $letter) {
                    if($letter === $guess) {
                        $correctGuess = true;
                        $client->correctGuesses[$index] = $letter;
                    }
                }

                if($correctGuess) {
                    $this->server->broadcast("\n\e[93m$client->name has guessed a letter correctly\e[39m\n", Response::MESSAGE_INFO);
                    if($this->hasClientWon($client)) {
                        $this->gameState = self::GAME_STATE_ENDED;
                        $this->server->broadcast("\n\n\e[92m$client->name has won the game\e[39m\n\n", Response::MESSAGE_INFO);
                        $this->server->broadcast("\e[92mThe word was $this->word\e[39m\n\n", Response::MESSAGE_INFO, false);
                        break;
                    }
                    continue;
                }

                $this->handleIncorrectGuess($client);
            }
        }
        $this->restartGame();
    }

    function formatAttemptString($client)
    {
        $attemptStr = "\n";
        for($i = 0; $i < $client->attempts; $i++) {
            $attemptStr .= "\e[91mX ";
        }

        $remainingAttempts = self::TOTAL_ATTEMPTS - $client->attempts;
        for($i = 0; $i < $remainingAttempts; $i++) {
            $attemptStr .= "\e[90m- ";
        }

        $attemptStr .= "\e[39m\n\n";

        return $attemptStr;
    }

    function formatCorrectGuesses($client)
    {
        $guessStr = "";
        $length = strlen($this->word);

        for($i = 0; $i < $length; $i++) {
            $guess = $client->correctGuesses[$i];

            if(trim($guess) === "") {
                $guessStr .= "\e[90m_ ";
                continue;
            }

            $guessStr .= "\e[32m$guess ";
        }

        $guessStr .= "\e[39m\n\n";

        return $guessStr;
    }

    function formatGuesses($client)
    {
        if(count($client->guesses) === 0) {
            return "";
        }

        $guessStr = "\n\nGuesses: ";
        $length = count($client->guesses);

        for($i = 0; $i < $length; $i++) {
            $guess = $client->guesses[$i];
            $guessStr .= "$guess";
            if($i !== $length) {
                $guessStr .= ", ";
            }
        }

        $guessStr .= "\e[39m\n";

        return $guessStr;
    }

    function handleIncorrectGuess($client)
    {
        $client->attempts += 1;
        if($this->hasClientLost($client)) {
            $this->server->sendMessage($client->connection, "\nYou have no attempts left\n", Response::MESSAGE_INFO);
            $this->server->broadcast("$client->name is out of the game\n\n", Response::MESSAGE_INFO);
            $this->server->sendMessage($client->connection, "The word was $this->word\n\n", Response::MESSAGE_INFO);
            return;
        }

        $remainingAttempts = self::TOTAL_ATTEMPTS - $client->attempts;
        $this->server->sendMessage($client->connection, "\nYour guess is incorrect\n", Response::MESSAGE_INFO);
        echo "$client->name has $remainingAttempts attempts left\n\n";
    }

    function hasClientLost($client)
    {
        return $client->attempts === self::TOTAL_ATTEMPTS;
    }

    function hasClientWon($client) {
        return implode("", $client->correctGuesses) === $this->word;
    }

    function resetClientAttempts()
    {
        foreach ($this->server->clients as $client) {
            $client->attempts = 0;
        }
    }

    function resetCorrectGuesses()
    {
        $correctGuesses = array_fill(0, strlen($this->word) - 1, "");

        foreach ($this->server->clients as $client) {

            $client->correctGuesses = $correctGuesses;
        }
    }

    function resetClientGuesses()
    {
        foreach ($this->server->clients as $client) {

            $client->guesses = [];
        }
    }
}