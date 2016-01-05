<?php

// Make sure Composer has been set up (for installation from Git, mostly).
if (!file_exists(__DIR__ . '/vendor/autoload.php')) {
    echo '<p>Please run <tt>composer install</tt> prior to using Tabulate.</p>';
    return;
}
require __DIR__ . '/vendor/autoload.php';

\Eloquent\Asplode\Asplode::install();

new Tabulate\Session();
