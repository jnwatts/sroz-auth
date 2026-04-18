Just a fairly simple authentication and authorization backend. It's intended to be used with nginx's `auth_request` mechanism:

```
location /auth_check.php {
    auth_request off;
    include php_params;
    internal;
}
location @error401 {
    return 302 $scheme://$server_name/auth.php?redirect=$request_uri;
}
location = /auth.php {
    auth_request off;
    include php_params;
}

location / {
    auth_request /auth_check.php;
    auth_request_set $authentication_id $upstream_http_x_authentication_id;
    proxy_set_header X-Authentication-Id: $authentication_id;
    error_page 401 = @error401;

    location ~ \.php$ {
        include php_params;
        fastcgi_param HTTP_X_AUTHENTICATION_ID $authentication_id;
    }

...
}
```
