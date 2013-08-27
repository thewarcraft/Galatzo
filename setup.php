<?php

/**
 * Variable global on emmagatzemam la configuració de l'aplicació.
 */
global $CFG;

// Agafam el directori arrel on està ubicada la aplicació.
$CFG->dirroot = dirname(dirname(__FILE__));

// wwwroot es obligatori.
if (!isset($CFG->wwwroot)) {
    if (isset($_SERVER['REMOTE_ADDR'])) {
        header($_SERVER['SERVER_PROTOCOL'] . ' 503 Service Unavailable');
    }
    echo('Error greu: $CFG->wwwroot no està configurat.'."\n");
    exit(1);
}