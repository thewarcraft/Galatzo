<?php
/**
 * Llibreria per accedir a la base de dades.
 * 
 * Actualment sols es soporta MySQL.
 * 
 * @author Toni Mas
 * @copyright 2013 Toni Mas <antoni.mas@gmail.com>
 */

defined('GALATZO_INTERNAL') || die();


/**
 * Configura la connexió a la BD.
 *
 * @global stdClass $CFG Configuració de Galatzó.
 * @global stdClass $DB Accés a la BD.
 * @return void|bool Retorna vertader quan es configura adequadament la BD, en canvi no fa
 * 						res si ja està configurat.
 */
function setup_DB() {
    global $CFG, $DB;

    if (isset($DB)) {
        return;
    }

    if (!isset($CFG->dbuser)) {
        $CFG->dbuser = '';
    }

    if (!isset($CFG->dbpass)) {
        $CFG->dbpass = '';
    }

    if (!isset($CFG->dbname)) {
        $CFG->dbname = '';
    }

    if (!isset($CFG->dblibrary)) {
        $CFG->dblibrary = 'native';
        switch ($CFG->dbtype) {
            case 'postgres7' :
                $CFG->dbtype = 'pgsql';
                break;

            case 'mysql' :
                $CFG->dbtype = 'mysqli';
                break;
        }
    }

    if (!isset($CFG->dboptions)) {
        $CFG->dboptions = array();
    }

    if (isset($CFG->dbpersist)) {
        $CFG->dboptions['dbpersist'] = $CFG->dbpersist;
    }

    if (!$DB = galatzo_database::get_driver_instance($CFG->dbtype, $CFG->dblibrary)) {
        throw new db_exception('dbdriverproblem', "Driver $CFG->dblibrary/$CFG->dbtype desconegut");
    }

    try {
        $DB->connect($CFG->dbhost, $CFG->dbuser, $CFG->dbpass, $CFG->dbname, $CFG->prefix, $CFG->dboptions);
    } catch (galatzo_exception $e) {
        // rethrow la excepció.
        throw $e;
    }

    $CFG->dbfamily = $DB->get_dbfamily();

    return true;
}