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
        throw new coding_exception('required_param() requires $parname and $type to be specified (parameter: '.$parname.')');
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
        throw new coding_exception('optional_param() requires $parname, $default and $type to be specified (parameter: '.$parname.')');
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
        throw new coding_exception('clean_param() can not process arrays, please use clean_param_array() instead.');
    } else if (is_object($param)) {
        if (method_exists($param, '__toString')) {
            $param = $param->__toString();
        } else {
            throw new coding_exception('clean_param() can not process objects, please use clean_param_array() instead.');
        }
    }

    switch ($type) {
        case PARAM_RAW:          // no cleaning at all
            $param = fix_utf8($param);
            return $param;

        case PARAM_RAW_TRIMMED:         // no cleaning, but strip leading and trailing whitespace.
            $param = fix_utf8($param);
            return trim($param);

        case PARAM_CLEAN:        // General HTML cleaning, try to use more specific type if possible
            // this is deprecated!, please use more specific type instead
            if (is_numeric($param)) {
                return $param;
            }
            $param = fix_utf8($param);
            return clean_text($param);     // Sweep for scripts, etc

        case PARAM_FLOAT:
        case PARAM_NUMBER:
            return (float)$param;  // Convert to float

        case PARAM_ALPHA:        // Remove everything not a-z
            return preg_replace('/[^a-zA-Z]/i', '', $param);

        case PARAM_ALPHAEXT:     // Remove everything not a-zA-Z_- (originally allowed "/" too)
            return preg_replace('/[^a-zA-Z_-]/i', '', $param);

        case PARAM_ALPHANUM:     // Remove everything not a-zA-Z0-9
            return preg_replace('/[^A-Za-z0-9]/i', '', $param);

        case PARAM_ALPHANUMEXT:     // Remove everything not a-zA-Z0-9_-
            return preg_replace('/[^A-Za-z0-9_-]/i', '', $param);

        case PARAM_BOOL:         // Convert to 1 or 0
            $tempstr = strtolower($param);
            if ($tempstr === 'on' or $tempstr === 'yes' or $tempstr === 'true') {
                $param = 1;
            } else if ($tempstr === 'off' or $tempstr === 'no'  or $tempstr === 'false') {
                $param = 0;
            } else {
                $param = empty($param) ? 0 : 1;
            }
            return $param;

        case PARAM_NOTAGS:       // Strip all tags
            $param = fix_utf8($param);
            return strip_tags($param);

        case PARAM_TEXT:    // leave only tags needed for multilang
            $param = fix_utf8($param);
            // if the multilang syntax is not correct we strip all tags
            // because it would break xhtml strict which is required for accessibility standards
            // please note this cleaning does not strip unbalanced '>' for BC compatibility reasons
            do {
                if (strpos($param, '</lang>') !== false) {
                    // old and future mutilang syntax
                    $param = strip_tags($param, '<lang>');
                    if (!preg_match_all('/<.*>/suU', $param, $matches)) {
                        break;
                    }
                    $open = false;
                    foreach ($matches[0] as $match) {
                        if ($match === '</lang>') {
                            if ($open) {
                                $open = false;
                                continue;
                            } else {
                                break 2;
                            }
                        }
                        if (!preg_match('/^<lang lang="[a-zA-Z0-9_-]+"\s*>$/u', $match)) {
                            break 2;
                        } else {
                            $open = true;
                        }
                    }
                    if ($open) {
                        break;
                    }
                    return $param;

                } else if (strpos($param, '</span>') !== false) {
                    // current problematic multilang syntax
                    $param = strip_tags($param, '<span>');
                    if (!preg_match_all('/<.*>/suU', $param, $matches)) {
                        break;
                    }
                    $open = false;
                    foreach ($matches[0] as $match) {
                        if ($match === '</span>') {
                            if ($open) {
                                $open = false;
                                continue;
                            } else {
                                break 2;
                            }
                        }
                        if (!preg_match('/^<span(\s+lang="[a-zA-Z0-9_-]+"|\s+class="multilang"){2}\s*>$/u', $match)) {
                            break 2;
                        } else {
                            $open = true;
                        }
                    }
                    if ($open) {
                        break;
                    }
                    return $param;
                }
            } while (false);
            // easy, just strip all tags, if we ever want to fix orphaned '&' we have to do that in format_string()
            return strip_tags($param);

        case PARAM_AUTH:
            $param = clean_param($param, PARAM_PLUGIN);
            if (empty($param)) {
                return '';
            } else if (exists_auth_plugin($param)) {
                return $param;
            } else {
                return '';
            }

        case PARAM_LANG:
            $param = clean_param($param, PARAM_SAFEDIR);
            if (get_string_manager()->translation_exists($param)) {
                return $param;
            } else {
                return ''; // Specified language is not installed or param malformed
            }

        case PARAM_USERNAME:
            $param = fix_utf8($param);
            $param = str_replace(" " , "", $param);
            $param = textlib::strtolower($param);  // Convert uppercase to lowercase MDL-16919
            if (empty($CFG->extendedusernamechars)) {
                // regular expression, eliminate all chars EXCEPT:
                // alphanum, dash (-), underscore (_), at sign (@) and period (.) characters.
                $param = preg_replace('/[^-\.@_a-z0-9]/', '', $param);
            }
            return $param;

        case PARAM_EMAIL:
            $param = fix_utf8($param);
            if (validate_email($param)) {
                return $param;
            } else {
                return '';
            }

        case PARAM_STRINGID:
            if (preg_match('|^[a-zA-Z][a-zA-Z0-9\.:/_-]*$|', $param)) {
                return $param;
            } else {
                return '';
            }

        default:                 // throw error, switched parameters in optional_param or another serious problem
            print_error("unknownparamtype", '', '', $type);
    }
}

/**
 * Makes sure the data is using valid utf8, invalid characters are discarded.
 *
 * Note: this function is not intended for full objects with methods and private properties.
 *
 * @param mixed $value
 * @return mixed with proper utf-8 encoding
 */
function fix_utf8($value) {
    if (is_null($value) or $value === '') {
        return $value;

    } else if (is_string($value)) {
        if ((string)(int)$value === $value) {
            // shortcut
            return $value;
        }

        // Lower error reporting because glibc throws bogus notices.
        $olderror = error_reporting();
        if ($olderror & E_NOTICE) {
            error_reporting($olderror ^ E_NOTICE);
        }

        // Note: this duplicates min_fix_utf8() intentionally.
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
                // Warn admins on admin/index.php page.
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
        $value = clone($value); // do not modify original
        foreach ($value as $k=>$v) {
            $value->$k = fix_utf8($v);
        }
        return $value;

    } else {
        // this is some other type, no utf-8 here
        return $value;
    }
}

/**
 * Return true if given value is integer or string with integer value
 *
 * @param mixed $value String or Int
 * @return bool true if number, false if not
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