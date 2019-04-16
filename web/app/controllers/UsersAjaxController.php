<?php
/**
 * @author Nicolas CARPi <nicolas.carpi@curie.fr>
 * @copyright 2012 Nicolas CARPi
 * @see https://www.elabftw.net Official website
 * @license AGPL-3.0
 * @package elabftw
 */
declare(strict_types=1);

namespace Elabftw\Elabftw;

use Elabftw\Exceptions\DatabaseErrorException;
use Elabftw\Exceptions\FilesystemErrorException;
use Elabftw\Exceptions\IllegalActionException;
use Elabftw\Exceptions\ImproperActionException;
use Elabftw\Models\Users;
use Exception;
use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Users info from admin or sysadmin page with ajax request and json response
 */
require_once \dirname(__DIR__) . '/init.inc.php';

$Response = new JsonResponse();
$Response->setData(array(
    'res' => true,
    'msg' => _('Saved')
));

try {
    // CSRF
    $App->Csrf->validate();

    // VALIDATE USER
    if ($Request->request->has('usersValidate')) {

        // you need to be at least admin to validate a user
        if (!$Session->get('is_admin')) {
            throw new IllegalActionException('Non admin user tried to validate user.');
        }

        // we need Config to send email. TODO make better constructors so we don't have to worry about that
        $targetUser = new Users((int) $Request->request->get('userid'), null, $App->Config);

        // check we validate user of our team
        if (($App->Users->userData['team'] !== $targetUser->userData['team']) && !$Session->get('is_sysadmin')) {
            throw new IllegalActionException('User tried to validate user from other team.');
        }

        // all good, validate user
        $targetUser->validate();
    }

    // ARCHIVE USER
    if ($Request->request->has('usersArchive')) {

        // you need to be at least admin to archive a user
        if (!$Session->get('is_admin')) {
            throw new IllegalActionException('Non admin user tried to archive another user.');
        }
        $targetUser = new Users((int) $Request->request->get('userid'));

        if ($targetUser->userData['validated'] === '0') {
            throw new ImproperActionException('You are trying to archive an unvalidated user. Maybe you want to delete the account?');
        }

        $targetUser->archive();
    }


    // DESTROY
    if ($Request->request->has('usersDestroy')) {

        // you need to be at least admin to delete a user
        if (!$Session->get('is_admin')) {
            throw new IllegalActionException('Non admin user tried to delete another user.');
        }

        // load data on the user to delete
        $targetUser = new Users((int) $Request->request->get('userid'));
        if (empty($targetUser)) {
            throw new IllegalActionException('User tried to delete inexisting user.');
        }

        // need to be sysadmin to delete user from other team
        if (($App->Users->userData['team'] !== $targetUser->userData['team']) && !$Session->get('is_sysadmin')) {
            throw new IllegalActionException('Admin user tried to delete user from other team.');
        }

        $targetUser->destroy();
    }

} catch (ImproperActionException $e) {
    $Response->setData(array(
        'res' => false,
        'msg' => $e->getMessage()
    ));

} catch (IllegalActionException $e) {
    $App->Log->notice('', array(array('userid' => $App->Session->get('userid')), array('IllegalAction', $e)));
    $Response->setData(array(
        'res' => false,
        'msg' => Tools::error(true)
    ));

} catch (DatabaseErrorException | FilesystemErrorException $e) {
    $App->Log->error('', array(array('userid' => $App->Session->get('userid')), array('Error', $e)));
    $Response->setData(array(
        'res' => false,
        'msg' => $e->getMessage()
    ));

} catch (Exception $e) {
    $App->Log->error('', array(array('userid' => $App->Session->get('userid')), array('Exception' => $e)));
    $Response->setData(array(
        'res' => false,
        'msg' => Tools::error()
    ));

} finally {
    $Response->send();
}
