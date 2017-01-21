<form method="post" action="<?=$_SERVER["PHP_SELF"]?>">
<input name="redirect" type="hidden" value="<?=$redirect?>">
<input name="username" type="text" value="<?=$session->username()?>"><br>
<input name="password" type="password"><br>
<input type="submit">
</form>
