<?php

// Keep this tiny front controller when serving the project from
// http://localhost/tradeflow. Laravel's actual public entry point remains
// public/index.php; loading it from here gives Laravel the correct base URL
// without exposing /public in application links.
require __DIR__.'/public/index.php';
