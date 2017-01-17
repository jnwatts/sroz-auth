<html>
<head>
    <script src="auth.js"></script>
    <script>
    $(function() {
    <?foreach ($js_env as $name => $value) {?>
        <?=$name?> = <?=json_encode($value)?>;
    <?}?>
    });
    </script>
</head>
<body>
<div id="msg"></div>
