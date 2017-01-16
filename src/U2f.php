<?
namespace Auth;

class U2f {
    private $config;
    private $lib;

    public function __construct(&$config) {
        $this->config =& $config;
        $this->lib = new \u2flib_server\U2F($config["u2f"]["appId"]);
    }

    public function registrations() {
        return isset($_SESSION['u2f_registrations']) ? $_SESSION['u2f_registrations'] : [];
    }

    public function keyHandles() {
        $reg = $this->registrations();
        $keyHandles = [];
        foreach ($reg as $r) {
            $keyHandles[] = $r->keyHandle;
        }
        return $keyHandles;
    }

    public function addRegistration($new_reg) {
        $regs = $this->registrations();
        $found = false;

        foreach ($regs as $reg) {
            if ($reg->keyHandle == $new_reg->keyHandle) {
                $reg = $new_reg;
                $found = true;
                break;
            }
        }

        if (!$found)
            $regs[] = $new_reg;

        $_SESSION['u2f_registrations'] = $regs;
    }

    public function removeRegistration($keyHandle) {
        $regs = $this->registrations();
        $found = false;

        foreach ($regs as $i => &$reg) {
            if ($reg->keyHandle == $keyHandle) {
                unset($regs[$i]);
            }
        }

        $_SESSION['u2f_registrations'] = $regs;
    }

    public function register($reg_request = null, $reg_response = null) {
        if (!$reg_request) {
            list($reg, $sign) = $this->lib->getRegisterData($this->registrations());
            return ["reg" => $reg, "sign" => $sign];
        } else {
            $reg = $this->lib->doRegister($reg_request, $reg_response);
            $this->addRegistration($reg);
        }
    }
}