<?
namespace Auth;

class User {
    public $name;
    public $hash;

    public function __construct($user) {
        $this->name = $user["name"];
        //TODO: Move $hash to DB? pass $users into constructor? Move validate 
        $this->hash = $user["hash"];
    }

}

