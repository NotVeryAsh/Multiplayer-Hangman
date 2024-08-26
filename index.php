<?php

// TODO Whenever sending a message to a client, make client responds so we know client is still active

include "Client.php";

$client = new Client();
$client->setup();