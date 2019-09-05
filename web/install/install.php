<?php declare(strict_types=1);
/**
 * install/install.php
 *
 * @author Nicolas CARPi <nicolas.carpi@curie.fr>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */

/**
 * This file reads info from POST and creates the config.php file (unless it exists)
 *
 */

namespace Elabftw\Elabftw;

use Defuse\Crypto\Key;
use Elabftw\Exceptions\IllegalActionException;
use Elabftw\Exceptions\ImproperActionException;
use Exception;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\Session\Session;

require_once \dirname(__DIR__, 2) . '/vendor/autoload.php';
$configFilePath = \dirname(__DIR__, 2) . '/config.php';

try {
    // create Request object
    $Request = Request::createFromGlobals();

    $Session = new Session();
    if (!$Request->hasPreviousSession()) {
        $Session->start();
    }

    // we disable errors to avoid having notice and warning polluting our file
    error_reporting(E_ERROR);

    // Check if there is already a config file, redirect to index if yes.
    if (file_exists($configFilePath)) {
        header('Location: ../install/index.php');
        throw new ImproperActionException('Redirecting to install page');
    }

    // POST data
    if (isset($_POST['db_host']) && !empty($_POST['db_host'])) {
        $db_host = $_POST['db_host'];
    } else {
        throw new IllegalActionException('Bad POST data');
    }

    if (isset($_POST['db_name']) && !empty($_POST['db_name'])) {
        $db_name = $_POST['db_name'];
    } else {
        throw new IllegalActionException('Bad POST data');
    }

    if (isset($_POST['db_user']) && !empty($_POST['db_user'])) {
        $db_user = $_POST['db_user'];
    } else {
        throw new IllegalActionException('Bad POST data');
    }

    // the db pass can be empty on mac and windows install
    if (isset($_POST['db_password']) && !empty($_POST['db_password'])) {
        $db_password = $_POST['db_password'];
    }

    // BUILD CONFIG FILE

    // make a new secret key
    $new_key = Key::createNewRandomKey();

    // what we will write in the file
    $config = "<?php
    define('DB_HOST', '" . $db_host . "');
    define('DB_NAME', '" . $db_name . "');
    define('DB_USER', '" . $db_user . "');
    define('DB_PASSWORD', '" . $db_password . "');
    define('SECRET_KEY', '" . $new_key->saveToAsciiSafeString() . "');";

    // we try to write content to file and propose the file for download if we can't write to it
    if (file_put_contents($configFilePath, $config)) {
        // it's cool, we managed to write the config file
        // let's put restricting permissions on it as discussed in #129
        if (is_writable($configFilePath)) {
            chmod($configFilePath, 0400);
        }
        $msg = 'Congratulations, you successfully installed eLabFTW, 
        now you need to <strong>register</strong> your account (you will have full admin rights).';
        $Session->getFlashBag()->add('ok', $msg);
        // redirect to install/index.php to import SQL structure
        header('Location: index.php');
    } else {
        // send the file
        $Response = new Response($config);

        $disposition = $Response->headers->makeDisposition(
            ResponseHeaderBag::DISPOSITION_ATTACHMENT,
            'config.php'
        );

        $Response->headers->set('Content-Disposition', $disposition);
        $Response->send();
    }

    // just show everything here
} catch (IllegalActionException | ImproperActionException | Exception $e) {
    echo Tools::displayMessage('Error: ' . $e->getMessage(), 'ko', false);
}
