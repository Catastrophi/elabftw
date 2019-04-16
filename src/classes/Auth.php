<?php
/**
 * @package   Elabftw\Elabftw
 * @author    Nicolas CARPi <nicolas.carpi@curie.fr>
 * @copyright 2012 Nicolas CARPi
 * @license   https://www.gnu.org/licenses/agpl-3.0.html AGPL-3.0
 * @see       https://www.elabftw.net Official website
 */
declare(strict_types=1);

namespace Elabftw\Elabftw;

use Elabftw\Exceptions\DatabaseErrorException;
use Elabftw\Exceptions\ImproperActionException;
use PDO;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

/**
 * Provide methods to login a user
 */
class Auth
{
    /** the minimum password length */
    public const MIN_PASSWORD_LENGTH = 8;

    /** @var Db $Db SQL Database */
    protected $Db;

    /** @var Request $Request current request */
    private $Request;

    /** @var SessionInterface $Session the current session */
    private $Session;

    /** @var array $userData All the user data for a user */
    private $userData = array();

    /**
     * Constructor
     *
     * @param Request $request
     * @param SessionInterface $session
     */
    public function __construct(Request $request, SessionInterface $session)
    {
        $this->Db = Db::getConnection();
        $this->Request = $request;
        $this->Session = $session;
    }

    /**
     * Get the salt for the user so we can generate a correct hash
     *
     * @param string $email
     * @return string
     */
    private function getSalt(string $email): string
    {
        $sql = "SELECT salt FROM users WHERE email = :email AND archived = 0";
        $req = $this->Db->prepare($sql);
        $req->bindParam(':email', $email);
        if ($req->execute() !== true) {
            throw new DatabaseErrorException('Error while executing SQL query.');
        }
        $res = $req->fetchColumn();
        if ($res === false) {
            throw new ImproperActionException(_("Login failed. Either you mistyped your password or your account isn't activated yet."));
        }
        return $res;
    }

    /**
     * Login with the cookie
     *
     * @return bool true if token in cookie is found in database
     */
    private function loginWithCookie(): bool
    {
        // If user has a cookie; check cookie is valid
        // the token is a sha256 sum: 64 char
        if (!$this->Request->cookies->has('token') || \mb_strlen($this->Request->cookies->get('token')) !== 64) {
            return false;
        }
        $token = $this->Request->cookies->filter('token', null, FILTER_SANITIZE_STRING);

        // Now compare current cookie with the token from SQL
        $sql = "SELECT * FROM users WHERE token = :token LIMIT 1";
        $req = $this->Db->prepare($sql);
        $req->bindParam(':token', $token);

        if ($req->execute() !== true) {
            throw new DatabaseErrorException('Error while executing SQL query.');
        }
        if ($req->rowCount() === 1) {
            $this->userData = $req->fetch();
            return true;
        }

        return false;
    }

    /**
     * Update last login time of user
     *
     * @return void
     */
    private function updateLastLogin(): void
    {
        $sql = "UPDATE users SET last_login = :last_login WHERE userid = :userid";
        $req = $this->Db->prepare($sql);
        $req->bindValue(':last_login', \date('Y-m-d H:i:s'));
        $req->bindParam(':userid', $this->userData['userid']);
        if ($req->execute() !== true) {
            throw new DatabaseErrorException('Error while executing SQL query.');
        }
    }

    /**
     * Populate userData from email
     *
     * @param string $email
     * @return bool
     */
    private function populateUserDataFromEmail(string $email): bool
    {
        $sql = "SELECT * FROM users WHERE email = :email AND archived = 0";
        $req = $this->Db->prepare($sql);
        $req->bindParam(':email', $email);
        if ($req->execute() !== true) {
            throw new DatabaseErrorException('Error while executing SQL query.');
        }
        if ($req->rowCount() === 1) {
            // populate the userData
            $this->userData = $req->fetch();
            $this->updateLastLogin();
            return true;
        }
        return false;
    }

    /**
     * Store userid and permissions in session
     *
     * @return void
     */
    private function populateSession(): void
    {
        $this->Session->set('auth', 1);
        $this->Session->set('userid', $this->userData['userid']);

        // load permissions
        $sql = "SELECT * FROM `groups` WHERE id = :id LIMIT 1";
        $req = $this->Db->prepare($sql);
        $req->bindParam(':id', $this->userData['usergroup'], PDO::PARAM_INT);
        if ($req->execute() !== true) {
            throw new DatabaseErrorException('Error while executing SQL query.');
        }
        $group = $req->fetch(PDO::FETCH_ASSOC);

        $this->Session->set('is_admin', $group['is_admin']);
        $this->Session->set('is_sysadmin', $group['is_sysadmin']);
    }

    /**
     * Set a $_COOKIE['token'] and update the database with this token.
     * Works only in HTTPS, valable for 1 month.
     * 1 month = 60*60*24*30 =  2592000
     *
     * @return void
     */
    private function setToken(): void
    {
        $token = \hash('sha256', \bin2hex(\random_bytes(16)));

        // create cookie
        // name, value, expire, path, domain, secure, httponly
        \setcookie('token', $token, time() + 2592000, '/', '', true, true);

        // Update the token in SQL
        $sql = "UPDATE users SET token = :token WHERE userid = :userid";
        $req = $this->Db->prepare($sql);
        $req->bindParam(':token', $token);
        $req->bindParam(':userid', $this->userData['userid'], PDO::PARAM_INT);

        if ($req->execute() !== true) {
            throw new DatabaseErrorException('Error while executing SQL query.');
        }
    }

    /**
     * Check the number of character of a password
     *
     * @param string $password The password to check
     * @throws ImproperActionException
     * @return bool
     */
    public function checkPasswordLength(string $password): bool
    {
        if (\mb_strlen($password) < self::MIN_PASSWORD_LENGTH) {
            throw new ImproperActionException(sprintf(_('Password must contain at least %s characters.'), self::MIN_PASSWORD_LENGTH));
        }
        return true;
    }

    /**
     * Test email and password in the database
     *
     * @param string $email
     * @param string $password
     * @return bool True if the login + password are good
     */
    public function checkCredentials(string $email, string $password): bool
    {
        $passwordHash = hash('sha512', $this->getSalt($email) . $password);

        $sql = "SELECT * FROM users WHERE email = :email AND password = :passwordHash
            AND validated = 1 AND archived = 0";
        $req = $this->Db->prepare($sql);
        $req->bindParam(':email', $email);
        $req->bindParam(':passwordHash', $passwordHash);

        if ($req->execute() !== true) {
            throw new DatabaseErrorException('Error while executing SQL query.');
        }
        return $req->rowCount() === 1;
    }

    /**
     * Login with email and password
     *
     * @param string $email
     * @param string $password
     * @param string $setCookie will be here if the user ticked the remember me checkbox
     * @return bool Return true if user provided correct credentials
     */
    public function login(string $email, string $password, string $setCookie = 'on'): bool
    {
        if ($this->checkCredentials($email, $password)) {
            $this->populateUserDataFromEmail($email);
            $this->populateSession();
            if ($setCookie === 'on') {
                $this->setToken();
            }
            return true;
        }
        return false;
    }

    /**
     * Login anonymously in a team
     *
     * @param int $team
     * @return void
     */
    public function loginAsAnon(int $team): void
    {
        $this->Session->set('anon', 1);
        $this->Session->set('team', $team);

        $this->Session->set('is_admin', 0);
        $this->Session->set('is_sysadmin', 0);
    }

    /**
     * Login with SAML. When this is called, user is authenticated with IDP
     *
     * @param string $email
     * @return bool
     */
    public function loginFromSaml(string $email): bool
    {
        if ($this->populateUserDataFromEmail($email)) {
            $this->populateSession();
            $this->setToken();
            return true;
        }
        return false;
    }

    /**
     * Check if we need to bother with authentication of current user
     *
     * @return bool True if we are authentified (or if we don't need to be)
     */
    public function needAuth(): bool
    {
        // pages where you don't need to be logged in
        // only the script name, not the path because we use basename() on it
        $nologinArr = array(
            'change-pass.php',
            'index.php',
            'login.php',
            'LoginController.php',
            'metadata.php',
            'register.php',
            'RegisterController.php',
            'ResetPasswordController.php'
        );

        return !\in_array(\basename($this->Request->getScriptName()), $nologinArr, true);
    }

    /**
     * Try to authenticate with session and cookie
     *     ____          _
     *    / ___|___ _ __| |__   ___ _ __ _   _ ___
     *   | |   / _ \ '__| '_ \ / _ \ '__| | | / __|
     *   | |___  __/ |  | |_) |  __/ |  | |_| \__ \
     *    \____\___|_|  |_.__/ \___|_|   \__,_|___/
     *
     * @return bool true if we are authenticated
     */
    public function tryAuth(): bool
    {
        if ($this->Session->has('anon')) {
            return true;
        }
        // if we are already logged in with the session, skip everything
        if ($this->Session->has('auth')) {
            return true;
        }

        // now try to login with the cookie
        if ($this->loginWithCookie()) {
            // successful login thanks to our cookie friend
            $this->populateSession();
            return true;
        }

        return false;
    }
}
