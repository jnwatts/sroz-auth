<?
namespace Auth;

class User {
    public $name;

    public function __construct($user) {
        $this->name = $user["name"];
    }

}

