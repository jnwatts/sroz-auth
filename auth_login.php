<!DOCTYPE html>
<html>
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta http-equiv="X-UA-Compatible" content="IE=edge">
    <title>Auth</title>
    <link href="/bootstrap_3.3.7.min.css" rel="stylesheet" type="text/css">
</head>
<body>
<div class="container">

<div class="page-header">
    <h1>Auth</h1>
</div>

<?if ($auth_failure) {?>
<div class="alert alert-danger">Error: <?=$auth_failure?></div>
<?}?>

<form action="/auth.php" method="post">
<?if (isset($_REQUEST['redirect'])) {?>
    <input type="hidden" name="redirect" value="<?=$_REQUEST['redirect']?>"><br>
<?}?>
    <input type="text" class="form-control" placeholder="Username" name="user" required><br>
    <input type="password" class="form-control" placeholder="Password" name="pass" required><br>
    <button type="submit" class="form-control">Login</button>
</form>

</div>

<script src="/jquery-2.1.4.min.js" type="text/javascript"></script>
<script src="/bootstrap_3.3.7.min.js" type="text/javascript"></script>
</body>
</html>