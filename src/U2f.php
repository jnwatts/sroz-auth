<?
namespace Auth;

class U2f {
    private $config;
    private $lib;

    public function __construct(&$config) {
        $this->config =& $config;
        $this->lib = new \u2flib_server\U2F($this->api());

        if (session_status() == PHP_SESSION_NONE)
            session_start();
    }

    public function api() {
        return $this->config["u2f"]["appId"];
    }

    public function validRegistrationCount($user) {
        $count = 0;

        $regs = $this->registrations($user);
        foreach ($regs as $r) {
            if ($r->counter >= 0)
                $count++;
        }

        return $count;
    }

    public function registrations($user) {
        $regs = null;

        if (@isset($_SESSION['u2f_registrations'][$user->name]))
            @$regs = json_decode($_SESSION['u2f_registrations'][$user->name]);

        if ($regs === null)
            $regs = [];

        return $regs;
    }

    public function keyHandles($user) {
        $keyHandles = [];

        $regs = $this->registrations($user);
        foreach ($regs as $r) {
            $keyHandles[] = $r->keyHandle;
        }

        return $keyHandles;
    }

    public function addRegistration($user, $new_reg) {
        $found = false;

        $regs = $this->registrations($user);
        foreach ($regs as &$r) {
            if ($r->keyHandle == $new_reg->keyHandle) {
                $r = $new_reg;
                $found = true;
                break;
            }
        }

        if (!$found)
            $regs[] = $new_reg;

        $_SESSION['u2f_registrations'][$user->name] = json_encode($regs);
    }

    public function removeRegistration($user, $keyHandle) {
        $found = false;

        $regs = $this->registrations($user);
        foreach ($regs as $i => &$r) {
            if ($r->keyHandle == $keyHandle) {
                unset($regs[$i]);
            }
        }

        $_SESSION['u2f_registrations'][$user->name] = json_encode($regs);
    }

    public function register($user, $reg_request = null, $reg_response = null) {
        if (!$reg_request) {
            list($reg, $sign) = $this->lib->getRegisterData($this->registrations($user));
            return ["reg" => $reg, "sign" => $sign];
        } else {
            $reg = $this->lib->doRegister($reg_request, $reg_response);
            if ($reg)
                $this->addRegistration($user, $reg);
            return ($reg !== null);
            
        }
    }

    public function authenticate($user, $auth_request = null, $auth_response = null) {
        if (!$auth_request) {
            return $this->lib->getAuthenticateData($this->registrations($user));
        } else {
            $reg = $this->lib->doAuthenticate($auth_request, $this->registrations($user), $auth_response);
            if ($reg)
                $this->addRegistration($user, $reg);
            return ($reg !== null);
        }
    }
}
