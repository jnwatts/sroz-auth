<script>$(function() { auth.update_keys(auth.key_handles); });</script>
<h1><a href="/">Home</a> / Auth</h1>
<h2>TODO:</h2>
<ul>
    <li>Move users+access to DB (config file should only be token/path/db params)</li>
</ul>
<h2>Username: <?=$user->name?></h2>
<h2>U2F Keys</h2>
<p>
    <span id="key_handles"></span>
    <input id="register" value="Register U2F" type="button"><br>
    <input id="authenticate" value="Authenticate U2F" type="button"><br>
</p>
<form method="post" action="<?=$_SERVER["PHP_SELF"]?>"> <input name="logout" value="Logout" type="submit"> </form>