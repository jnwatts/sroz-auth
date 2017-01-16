<?
namespace Auth;

class Access {
    private $config;

    public function __construct(&$config) {
        $this->config =& $config;
    }

    public static function pathMatch($path, $pattern) {
        $length = strlen($pattern);
        return (substr($path, 0, $length) === $pattern);
    }

    function validate($user, $path) {
        $user_name = $user ? $user->name : "";
        foreach ($this->config['acl'] as $rule) {
            if (Access::pathMatch($path, $rule["path"])) {
                if (!isset($rule["valid_users"]))
                    return true;

                if (!in_array($user_name, $rule["valid_users"]))
                    return false;
            }
        }
        return true;
    }

}

