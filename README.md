Just a fairly simple authentication and authorization backend. It's intended to be used with nginx's `auth_request` mechanism:

```
location = /auth.php {
    auth_request off;
    include php_params;
}

location /auth_check.php {
    auth_request off;
    include php_params;
    internal;
}

location @error401 {
    return 302 $scheme://$server_name/auth.php?redirect=$request_uri;
}


location / {
    auth_request /auth_check.php;
    error_page 401 = @error401;
    ...
}
```
