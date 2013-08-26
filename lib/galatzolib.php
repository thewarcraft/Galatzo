<?php
/**
 * Llibreria principal de Galatzó.
 * 
 * @author Toni Mas
 * @copyright 2013 Toni Mas <antoni.mas@gmail.com>
 */
defined('GALATZO_INTERNAL') || die();


/**
 * Obliga que el paràmetre estigui present al $POST o $GET i del tipus que s'especifica.
 * En cas no estar-hi, retorna error. Retorna el valor que pren.
 *
 * @param string $parname El paràmetre que es vol.
 * @param string $type El tipus de paràmetre que s'espera.
 * @return mixed
 */
function required_param($parname, $type) {
    if (func_num_args() != 2 or empty($parname) or empty($type)) {
        throw new coding_exception('required_param() necessita $parname i $type ha de ser especificat (paràmetre: '.$parname.')');
    }
    if (isset($_POST[$parname])) {
        $param = $_POST[$parname];
    } else if (isset($_GET[$parname])) {
        $param = $_GET[$parname];
    } else {
        print_error('missingparam', '', '', $parname);
    }

    if (is_array($param)) {
        print_error('missingparam', '', '', $parname);
    }

    return clean_param($param, $type);
}

/**
 * Retorna el valor que agafa el paràmetre en cas d'existir.
 * Sempre se li pot posar un valor per defecte.
 *
 * @param string $parname El paràmetre
 * @param mixed  $default El valor per defecte quan no hi sigui.
 * @param string $type Tipus esperat.
 * @return mixed
 */
function optional_param($parname, $default, $type) {
    if (func_num_args() != 3 or empty($parname) or empty($type)) {
        throw new coding_exception('optional_param() necessita especificar $parname, $default i $type (paràmetre: '.$parname.')');
    }
    if (!isset($default)) {
        $default = null;
    }

    if (isset($_POST[$parname])) {
        $param = $_POST[$parname];
    } else if (isset($_GET[$parname])) {
        $param = $_GET[$parname];
    } else {
        return $default;
    }

    if (is_array($param)) {
        print_error('missingparam', '', '', $parname);
    }

    return clean_param($param, $type);
}

/**
 * Fa net.
 *
 * @param mixed $param Variable a fer net.
 * @param string $type Tipus de dades, es fa net segons el tipus que s'espera.
 * @return mixed
 */
function clean_param($param, $type) {

    global $CFG;

    if (is_array($param)) {
        throw new coding_exception('clean_param() no pot processar arrays arrays.');
    } else if (is_object($param)) {
        if (method_exists($param, '__toString')) {
            $param = $param->__toString();
        } else {
            throw new coding_exception('clean_param() no pot processar objectes.');
        }
    }

    switch ($type) {
        case PARAM_RAW:          // No fa res net.
            $param = fix_utf8($param);
            return $param;

        case PARAM_FLOAT:
        case PARAM_NUMBER:
            return (float)$param;  // Cast al tipus float.

        case PARAM_ALPHA:        // Elimina tot el que no sigui a-z.
            return preg_replace('/[^a-zA-Z]/i', '', $param);

        case PARAM_ALPHAEXT:     // Elimina tot el que no sigui a-zA-Z_-.
            return preg_replace('/[^a-zA-Z_-]/i', '', $param);

        case PARAM_ALPHANUM:     // Elimina tot el que no sigui a-zA-Z0-9.
            return preg_replace('/[^A-Za-z0-9]/i', '', $param);

        case PARAM_ALPHANUMEXT:     // Elimina tot el que no sigui a-zA-Z0-9_-.
            return preg_replace('/[^A-Za-z0-9_-]/i', '', $param);

        case PARAM_BOOL:         // Converteix a 0 o 1.
            $tempstr = strtolower($param);
            if ($tempstr === 'on' or $tempstr === 'yes' or $tempstr === 'true') {
                $param = 1;
            } else if ($tempstr === 'off' or $tempstr === 'no'  or $tempstr === 'false') {
                $param = 0;
            } else {
                $param = empty($param) ? 0 : 1;
            }
            return $param;

        case PARAM_TEXT:
            $param = fix_utf8($param);
            
            return strip_tags($param);

        case PARAM_USERNAME:
            $param = fix_utf8($param);
            $param = str_replace(" " , "", $param);
            $param = strtolower($param);
            $param = preg_replace('/[^-\.@_a-z0-9]/', '', $param);
            
            return $param;

        case PARAM_EMAIL:
            $param = fix_utf8($param);
            if (validate_email($param)) {
                return $param;
            } else {
                return '';
            }

        default:
            print_error("unknownparamtype", '', '', $type);
    }
}

/**
 * Assegura que les dades estiguin en utf8, qualsevol altra caràcter extrany queda descartat.
 *
 * @param mixed $value
 * @return mixed Text en UTF8.
 */
function fix_utf8($value) {
    if (is_null($value) or $value === '') {
        return $value;

    } else if (is_string($value)) {
        if ((string)(int)$value === $value) {
            // Paraula curta.
            return $value;
        }

        $olderror = error_reporting();
        if ($olderror & E_NOTICE) {
            error_reporting($olderror ^ E_NOTICE);
        }

        static $buggyiconv = null;
        if ($buggyiconv === null) {
            $buggyiconv = (!function_exists('iconv') or iconv('UTF-8', 'UTF-8//IGNORE', '100'.chr(130).'€') !== '100€');
        }

        if ($buggyiconv) {
            if (function_exists('mb_convert_encoding')) {
                $subst = mb_substitute_character();
                mb_substitute_character('');
                $result = mb_convert_encoding($value, 'utf-8', 'utf-8');
                mb_substitute_character($subst);

            } else {
                $result = $value;
            }

        } else {
            $result = iconv('UTF-8', 'UTF-8//IGNORE', $value);
        }

        if ($olderror & E_NOTICE) {
            error_reporting($olderror);
        }

        return $result;

    } else if (is_array($value)) {
        foreach ($value as $k=>$v) {
            $value[$k] = fix_utf8($v);
        }
        return $value;

    } else if (is_object($value)) {
        $value = clone($value); // No modificam res.
        foreach ($value as $k=>$v) {
            $value->$k = fix_utf8($v);
        }
        return $value;

    } else {
        // Si no és utf8.
        return $value;
    }
}

/**
 * Retorna vertader si és un enter o un String que sigui un nombre.
 *
 * @param mixed $value String o Int
 * @return bool Verdader si és un número, fals en cas contrari.
 */
function is_number($value) {
    if (is_int($value)) {
        return true;
    } else if (is_string($value)) {
        return ((string)(int)$value) === $value;
    } else {
        return false;
    }
}

/**
 * Requereix que l'usuari hagui accedit al sistema.
 *
 * @return mixed Redirecció a la plana de login en cas de no estar autenticat.
 */
function require_login() {
    global $CFG, $SESSION, $USER, $DB;
    
    // Si l'usuari no està autenticat.
    if (!isloggedin()) {
        //NOTE: $USER->site check was obsoleted by session test cookie,
        //      $USER->confirmed test is in login/index.php
        if ($preventredirect) {
            throw new require_login_exception('You are not logged in');
        }

        if ($setwantsurltome) {
            $SESSION->wantsurl = qualified_me();
        }
        if (!empty($_SERVER['HTTP_REFERER'])) {
            $SESSION->fromurl  = $_SERVER['HTTP_REFERER'];
        }
        redirect(get_login_url());
        exit; // No hem de fer res més.
    }
    
    // Cream la cookie d'accés.
    sesskey();
}

/**
 * Força la sortida d'un usuari.
 */
function require_logout() {
    global $USER;

    $params = $USER;

    if (isloggedin()) {
        add_to_log(SITEID, "user", "logout", "view.php?id=$USER->id&course=".SITEID, $USER->id, 0, $USER->id);

        $authsequence = get_enabled_auth_plugins(); // auths, in sequence
        foreach($authsequence as $authname) {
            $authplugin = get_auth_plugin($authname);
            $authplugin->prelogout_hook();
        }
    }

    events_trigger('user_logout', $params);
    session_get_instance()->terminate_current();
    unset($params);
}